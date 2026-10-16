<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dashboard/revenue_ibfm_processor.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Izinkan halaman lokal file:// mengirim upload ke server PHP di komputer ini.
// Browser mengirim Origin: null untuk file://. Origin web lain tetap ditolak.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$isHttps = $forwardedProto === 'https'
    || (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$sameOrigin = $requestHost !== ''
    && $origin === (($isHttps ? 'https' : 'http') . '://' . $requestHost);
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') {
        header('Access-Control-Allow-Private-Network: true');
    }
} elseif ($origin !== '' && !$sameOrigin && $origin !== 'http://127.0.0.1:8000' && $origin !== 'http://localhost:8000') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Origin tidak diizinkan.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Reject non-upload requests before touching the database. This also makes a
// POST-to-GET redirect visible as a clear endpoint error instead of allowing
// the request to continue through the upload setup.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint upload menerima ' . ($_SERVER['REQUEST_METHOD'] ?? 'metode tidak diketahui') . ', bukan POST. URL upload harus menuju upload/upload.php tanpa redirect.'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');



try {

    // Tentukan tabel hanya dari pilihan divisi yang diizinkan.
    $divisi = trim((string)($_POST['divisi'] ?? ''));
    $reportTables = [
        'IB' => 'revenue_ibfm',
        'Revenue Konimex Selling Out' => 'revenue_selling_out',
        'REVENUE VERSI EDP MNJ' => 'revenue_edp_mnj',
        'Revenue Konimex Selling In' => 'revenue_selling_in',
        'MTM' => 'mtm_monthly_values',
        'ETHICAL' => 'ETHICAL',
    ];
    if (!isset($reportTables[$divisi])) {
        http_response_code(400);
        throw new Exception('Divisi upload tidak valid.');
    }
    $tableName = $reportTables[$divisi];
    $replaceExistingPeriod = $divisi === 'REVENUE VERSI EDP MNJ'
        || $divisi === 'ETHICAL'
        || $divisi === 'IB'
        || $divisi === 'MTM'
        || ($divisi === 'Revenue Konimex Selling Out' && (($_POST['replace_existing'] ?? '') === '1'));

    // Periksa periode lebih dahulu agar periode yang sudah tersimpan tetap
    // menghasilkan pesan duplikat, meskipun bagian file tidak ikut terkirim.
    $period = trim((string)($_POST['period'] ?? $_GET['period'] ?? ''));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) || (int)substr($period, 0, 4) < 2025) {
        throw new Exception('Periode laporan tidak valid. Pilih periode dengan format tahun-bulan.');
    }

    // =========================
    // 1. CEK DATABASE / PERIODE
    // =========================

    $conn = app_database_connection();
    if ($conn->connect_error) {
        throw new Exception('Database gagal terhubung: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    if ($divisi === 'REVENUE VERSI EDP MNJ') {
        $createEdpTable = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NULL,
            gb VARCHAR(50) NULL,
            product_code VARCHAR(100) NULL,
            principal_product_code VARCHAR(100) NULL,
            product_name VARCHAR(255) NULL,
            kategori VARCHAR(100) NULL,
            qty_jan DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_feb DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_mar DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_apr DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_mei DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_jun DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_jul DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_agt DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_sep DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_okt DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_nov DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_des DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            rev_jan DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_feb DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_mar DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_apr DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_mei DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_jun DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_jul DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_agt DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_sep DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_okt DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_nov DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_des DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            KEY idx_revenue_edp_period (report_period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if (!$conn->query($createEdpTable)) {
            throw new Exception('Gagal menyiapkan tabel EDP MNJ: ' . $conn->error);
        }
        if (!$conn->query("CREATE TABLE IF NOT EXISTS `UNIT` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NULL, gb VARCHAR(50) NULL, product_code VARCHAR(100) NULL,
            principal_product_code VARCHAR(100) NULL, product_name VARCHAR(255) NULL, kategori VARCHAR(100) NULL,
            unit_jan DECIMAL(15,2) NOT NULL DEFAULT 0, unit_feb DECIMAL(15,2) NOT NULL DEFAULT 0,
            unit_mar DECIMAL(15,2) NOT NULL DEFAULT 0, unit_apr DECIMAL(15,2) NOT NULL DEFAULT 0,
            unit_mei DECIMAL(15,2) NOT NULL DEFAULT 0, unit_jun DECIMAL(15,2) NOT NULL DEFAULT 0,
            unit_jul DECIMAL(15,2) NOT NULL DEFAULT 0, unit_agt DECIMAL(15,2) NOT NULL DEFAULT 0,
            unit_sep DECIMAL(15,2) NOT NULL DEFAULT 0, unit_okt DECIMAL(15,2) NOT NULL DEFAULT 0,
            unit_nov DECIMAL(15,2) NOT NULL DEFAULT 0, unit_des DECIMAL(15,2) NOT NULL DEFAULT 0,
            unit_total DECIMAL(15,2) NOT NULL DEFAULT 0, KEY idx_unit_report_period (report_period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
            throw new Exception('Gagal menyiapkan tabel UNIT: ' . $conn->error);
        }
    }

    if ($divisi === 'MTM' && !$conn->query("CREATE TABLE IF NOT EXISTS `mtm_monthly_values` (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_period CHAR(7) NOT NULL,
        metric_code VARCHAR(24) NOT NULL,
        value_type VARCHAR(8) NOT NULL,
        month_number TINYINT UNSIGNED NOT NULL,
        amount DECIMAL(20,2) NULL,
        UNIQUE KEY uq_mtm_period_metric_type_month (report_period, metric_code, value_type, month_number),
        KEY idx_mtm_period_metric (report_period, metric_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) {
        throw new Exception('Gagal menyiapkan tabel MTM: ' . $conn->error);
    }

    if ($divisi === 'ETHICAL') {
        $createEthicalTable = "CREATE TABLE IF NOT EXISTS `ETHICAL` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_year SMALLINT UNSIGNED NOT NULL,
            source_row INT UNSIGNED NOT NULL,
            product_no VARCHAR(50) NULL,
            product_name VARCHAR(255) NULL,
            product_hna DECIMAL(20,2) NOT NULL DEFAULT 0,
            avg_current_year DECIMAL(20,2) NOT NULL DEFAULT 0,
            avg_previous_year DECIMAL(20,2) NOT NULL DEFAULT 0,
            estimated_total_previous_year DECIMAL(20,2) NOT NULL DEFAULT 0,
            g_value DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_target_unit DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_target_hna DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_target_hjp DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_real_unit DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_real_hna DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_real_hjp DECIMAL(20,2) NOT NULL DEFAULT 0,
            month_number TINYINT UNSIGNED NOT NULL,
            data_type ENUM('TARGET','REAL') NOT NULL,
            unit_value DECIMAL(20,2) NOT NULL DEFAULT 0,
            hna_value DECIMAL(20,2) NOT NULL DEFAULT 0,
            hjp_value DECIMAL(20,2) NOT NULL DEFAULT 0,
            source_filename VARCHAR(255) NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ethical_row_month_type (report_year, source_row, month_number, data_type),
            KEY idx_ethical_year_month (report_year, month_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if (!$conn->query($createEthicalTable)) {
            throw new Exception('Gagal menyiapkan tabel ETHICAL: ' . $conn->error);
        }
        // Tambah kolom total per TARGET/REAL pada tabel ETHICAL yang dibuat
        // dari versi skema awal, tanpa menghapus atau menimpa data lama.
        $ethicalTotalColumns = [
            'total_target_unit' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
            'total_target_hna' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
            'total_target_hjp' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
            'total_real_unit' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
            'total_real_hna' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
            'total_real_hjp' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
        ];
        foreach ($ethicalTotalColumns as $column => $definition) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM `ETHICAL` LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$columnCheck || $columnCheck->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `ETHICAL` ADD COLUMN `{$column}` {$definition}")) {
                    throw new Exception('Gagal menyiapkan kolom ' . $column . ' pada tabel ETHICAL: ' . $conn->error);
                }
            }
        }
    }

    if ($divisi === 'IB' && !$conn->query("CREATE TABLE IF NOT EXISTS `revenue_ibfm` (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_period CHAR(7) NOT NULL,
        month_number TINYINT UNSIGNED NOT NULL,
        target_ib_fm DECIMAL(20,2) NULL,
        actual_ib_fm DECIMAL(20,2) NULL,
        target_ib_fd DECIMAL(20,2) NULL,
        actual_ib_fd DECIMAL(20,2) NULL,
        target_new_ib_fm DECIMAL(20,2) NULL,
        actual_new_ib_fm DECIMAL(20,2) NULL,
        target_new_ib_fd DECIMAL(20,2) NULL,
        actual_new_ib_fd DECIMAL(20,2) NULL,
        target_mcm DECIMAL(20,2) NULL,
        actual_mcm DECIMAL(20,2) NULL,
        UNIQUE KEY unique_ibfm_period_month (report_period, month_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        throw new Exception('Gagal menyiapkan tabel IB FM: ' . $conn->error);
    }
    if ($divisi === 'IB') {
        foreach ([
            'target_ib_fd' => 'DECIMAL(20,2) NULL', 'actual_ib_fd' => 'DECIMAL(20,2) NULL',
            'target_new_ib_fm' => 'DECIMAL(20,2) NULL', 'actual_new_ib_fm' => 'DECIMAL(20,2) NULL',
            'target_new_ib_fd' => 'DECIMAL(20,2) NULL', 'actual_new_ib_fd' => 'DECIMAL(20,2) NULL',
            'target_mcm' => 'DECIMAL(20,2) NULL', 'actual_mcm' => 'DECIMAL(20,2) NULL',
        ] as $column => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM `revenue_ibfm` LIKE '{$column}'");
            if (!$check || $check->num_rows === 0) {
                if (!$conn->query("ALTER TABLE `revenue_ibfm` ADD COLUMN `{$column}` {$definition}")) {
                    throw new Exception('Gagal menyiapkan kolom IB FD: ' . $conn->error);
                }
            }
        }
        if (!$conn->query("CREATE TABLE IF NOT EXISTS `ib_monthly_values` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NOT NULL,
            month_number TINYINT UNSIGNED NOT NULL,
            metric_code VARCHAR(32) NOT NULL,
            business_unit VARCHAR(16) NOT NULL,
            value_type VARCHAR(8) NOT NULL,
            amount DECIMAL(20,2) NULL,
            UNIQUE KEY unique_ib_value (report_period, month_number, metric_code, business_unit, value_type),
            KEY idx_ib_period_metric (report_period, metric_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) {
            throw new Exception('Gagal menyiapkan tabel ib_monthly_values: ' . $conn->error);
        }
    }

    if ($divisi === 'Revenue Konimex Selling In') {
        if (!$conn->query("CREATE TABLE IF NOT EXISTS `{$tableName}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NOT NULL,
            parameter VARCHAR(100) NOT NULL,
            achievement DECIMAL(20,2) NOT NULL DEFAULT 0,
            UNIQUE KEY unique_period_parameter (report_period, parameter)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
            throw new Exception('Gagal menyiapkan tabel Selling In: ' . $conn->error);
        }
    } elseif ($divisi !== 'ETHICAL' && $divisi !== 'MTM') {
        $columnCheck = $conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'report_period'");
        if (!$columnCheck || $columnCheck->num_rows === 0) {
            if (!$conn->query("ALTER TABLE `{$tableName}` ADD COLUMN report_period CHAR(7) NULL")) {
                throw new Exception('Gagal menyiapkan kolom periode laporan: ' . $conn->error);
            }
        }
    }

    if ($divisi === 'REVENUE VERSI EDP MNJ') {
        $tagLegacyRows = $conn->prepare("UPDATE `{$tableName}` SET report_period = ? WHERE report_period IS NULL");
        if (!$tagLegacyRows) throw new Exception('Gagal menandai periode data EDP lama: ' . $conn->error);
        $tagLegacyRows->bind_param('s', $period);
        if (!$tagLegacyRows->execute()) throw new Exception('Gagal menandai periode data EDP lama: ' . $tagLegacyRows->error);
        $tagLegacyRows->close();
    }

    $existingRows = 0;
    if ($divisi !== 'ETHICAL') {
        $periodCheck = $conn->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE report_period = ?");
        if (!$periodCheck) {
            throw new Exception('Gagal mengecek periode laporan: ' . $conn->error);
        }
        $periodCheck->bind_param('s', $period);
        $periodCheck->execute();
        $periodCheck->bind_result($existingRows);
        $periodCheck->fetch();
        $periodCheck->close();
    }

    if ($existingRows > 0 && !$replaceExistingPeriod) {
        throw new Exception('Periode laporan ' . $period . ' sudah ada di database. Upload dibatalkan agar data tidak duplikat.');
    }

    // =========================
    // 2. CEK FILE
    // =========================

    if (!isset($_FILES['file'])) {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMax = trim((string)ini_get('post_max_size'));
        $postMaxBytes = preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])?B?$/i', $postMax, $m)
            ? (int)((float)$m[1] * match (strtoupper($m[2] ?? '')) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 })
            : 0;
        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            throw new Exception('Ukuran permintaan melebihi batas PHP post_max_size (' . $postMax . '). Jalankan server dengan batas upload minimal 52M.');
        }
        throw new Exception('File Excel belum dikirim. Pilih file kembali lalu coba upload.');
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(
            'Upload file gagal. Error code: ' . $_FILES['file']['error']
        );
    }

    $file = $_FILES['file']['tmp_name'];

    if ($divisi === 'MTM') {
        $spreadsheet = IOFactory::load($file);
        $monthAliases = [
            'JAN' => 1, 'JANUARI' => 1, 'FEB' => 2, 'FEBRUARI' => 2,
            'MAR' => 3, 'MARET' => 3, 'APR' => 4, 'APRIL' => 4,
            'MAY' => 5, 'MEI' => 5, 'JUN' => 6, 'JUNI' => 6,
            'JUL' => 7, 'JULI' => 7, 'AUG' => 8, 'AGS' => 8, 'AGU' => 8, 'AGUSTUS' => 8,
            'SEP' => 9, 'SEPTEMBER' => 9, 'OCT' => 10, 'OKT' => 10, 'OKTOBER' => 10,
            'NOV' => 11, 'NOVEMBER' => 11, 'DEC' => 12, 'DES' => 12, 'DESEMBER' => 12,
        ];
        $normalize = static function ($value): string {
            $text = str_replace("\xC2\xA0", ' ', (string)$value);
            return strtoupper(trim(preg_replace('/\\s+/u', ' ', $text)));
        };
        $mtmData = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sourceSheet) {
            $monthColumns = [];
            $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sourceSheet->getHighestDataColumn());
            $highestRow = $sourceSheet->getHighestDataRow();
            $grid = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $grid[$row] = [];
                for ($column = 1; $column <= $highestColumn; $column++) {
                    $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row;
                    $cell = $sourceSheet->getCell($coordinate);
                    $grid[$row][$column] = $normalize($cell->getValue());
                    if (isset($monthAliases[$grid[$row][$column]])) $monthColumns[$row][$monthAliases[$grid[$row][$column]]] = $column;
                }
            }
            $monthHeaderRows = [];
            foreach ($monthColumns as $row => $columns) {
                if (count($columns) === 12) $monthHeaderRows[] = (int)$row;
            }

            $rowText = static fn(array $cells): string => trim(implode(' ', array_filter($cells, static fn($value): bool => $value !== '')));
            $objectiveRow = null;
            foreach ($grid as $row => $cells) {
                if (str_contains($rowText($cells), 'INCREASE REVENUE')) { $objectiveRow = (int)$row; break; }
            }
            if ($objectiveRow === null) continue;
            if (!$monthHeaderRows) continue;
            usort($monthHeaderRows, static fn(int $left, int $right): int => abs($left - $objectiveRow) <=> abs($right - $objectiveRow));
            $headerRow = $monthHeaderRows[0];

            $isMetricLabel = static function (string $text, string $metric): bool {
                if ($metric === 'REVENUE') {
                    return (str_contains($text, 'KPI REVENUE') && (str_contains($text, 'RP') || str_contains($text, 'RUPIAH')))
                        || str_contains($text, 'REVENUE (RP)');
                }
                if ($metric === 'EXISTING') return str_contains($text, 'REVENUE EXISTING');
                return str_contains($text, 'REVENUE NEW PRODUCT');
            };
            $anchors = [];
            $unitRow = $highestRow + 1;
            foreach ($grid as $row => $cells) {
                if ($row <= $objectiveRow) continue;
                if (str_contains($rowText($cells), 'REVENUE (UNIT)')) { $unitRow = (int)$row; break; }
            }
            foreach (['REVENUE', 'EXISTING', 'NEW_PRODUCT'] as $metric) {
                foreach ($grid as $row => $cells) {
                    if ($row < $objectiveRow || $row >= $unitRow) continue;
                    if ($isMetricLabel($rowText($cells), $metric)) { $anchors[$metric] = (int)$row; break; }
                }
            }
            if (count($anchors) !== 3) continue;
            asort($anchors);
            $orderedMetrics = array_keys($anchors);
            foreach ($orderedMetrics as $metricIndex => $metric) {
                $startRow = $anchors[$metric];
                $endRow = $orderedMetrics[$metricIndex + 1] ?? null;
                $endRow = $endRow === null ? $unitRow : $anchors[$endRow];
                $typeRows = [];
                for ($row = $startRow; $row < $endRow; $row++) {
                    $labels = $rowText($grid[$row] ?? []);
                    if (preg_match('/\bTARGET\b/', $labels)) $typeRows['TARGET'] = $row;
                    if (preg_match('/\b(?:AKTUAL|ACTUAL)\b/', $labels)) $typeRows['ACTUAL'] = $row;
                }
                if (!isset($typeRows['TARGET'], $typeRows['ACTUAL'])) continue;
                foreach ($typeRows as $valueType => $row) {
                    $amounts = [];
                    foreach ($monthColumns[$headerRow] as $monthNumber => $column) {
                        $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row;
                        $cell = $sourceSheet->getCell($coordinate);
                        $value = $cell->getCalculatedValue();
                        $amounts[$monthNumber] = is_numeric($value) ? (float)$value : null;
                    }
                    $mtmData[$metric][$valueType] = $amounts;
                }
            }
            if (isset($mtmData['REVENUE']['TARGET'], $mtmData['REVENUE']['ACTUAL'], $mtmData['EXISTING']['TARGET'], $mtmData['EXISTING']['ACTUAL'], $mtmData['NEW_PRODUCT']['TARGET'], $mtmData['NEW_PRODUCT']['ACTUAL'])) break;
            $mtmData = [];
        }
        if (!$mtmData) throw new Exception('Bagian finansial MTM belum dikenali. Pastikan file memuat KPI Revenue (Rp), Revenue Existing, Revenue New Product, header Januari–Desember, serta baris Target dan Aktual di bawah Increase Revenue.');

        $conn->begin_transaction();
        $delete = $conn->prepare('DELETE FROM mtm_monthly_values WHERE report_period = ?');
        if (!$delete) throw new Exception('Gagal menyiapkan penggantian data MTM: ' . $conn->error);
        $delete->bind_param('s', $period);
        if (!$delete->execute()) throw new Exception('Data MTM lama gagal diganti: ' . $delete->error);
        $delete->close();

        $insert = $conn->prepare('INSERT INTO mtm_monthly_values (report_period, metric_code, value_type, month_number, amount) VALUES (?, ?, ?, ?, ?)');
        if (!$insert) throw new Exception('Gagal menyiapkan penyimpanan data MTM: ' . $conn->error);
        $inserted = 0;
        foreach ($mtmData as $metric => $types) {
            foreach ($types as $valueType => $amounts) {
                foreach ($amounts as $monthNumber => $amount) {
                    $month = (int)$monthNumber;
                    $insert->bind_param('sssid', $period, $metric, $valueType, $month, $amount);
                    if (!$insert->execute()) throw new Exception('Gagal menyimpan data MTM ' . $metric . ' bulan ' . $month . ': ' . $insert->error);
                    $inserted++;
                }
            }
        }
        $insert->close();
        $conn->commit();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Data MTM bagian Increase Revenue berhasil disimpan untuk periode ' . $period . '.', 'rows_inserted' => $inserted]);
        exit;
    }

    if ($divisi === 'IB') {
        // Pengguna dapat mengisi salah satu sheet lebih dahulu; simpan nilai
        // sheet yang tersedia dan biarkan kolom sheet lainnya kosong.
        $monthlyFm = processRevenueExistIbFmSpreadsheet($file, (int)substr($period, 0, 4), 'YFR - IBFM', true);
        $monthlyFd = processRevenueExistIbFmSpreadsheet($file, (int)substr($period, 0, 4), 'IAP', true);
        $monthlyNewFm = processRevenueExistIbFmSpreadsheet($file, (int)substr($period, 0, 4), 'YFR - IBFM', true, 'SALES REVENUE NEW BUSINESS PRODUCT (USD)', 9);
        $monthlyNewFd = processRevenueExistIbFmSpreadsheet($file, (int)substr($period, 0, 4), 'IAP', true, 'SALES REVENUE NEW BUSINESS PRODUCT (USD)', 9);
        $monthlyMcm = processRevenueExistIbFmSpreadsheet($file, (int)substr($period, 0, 4), 'HRU', true, 'MCM = REVENUE - HPP – EXPENSES', 15);
        $hasRevenueData = false;
        foreach ([$monthlyFm, $monthlyFd, $monthlyNewFm, $monthlyNewFd, $monthlyMcm] as $sourceData) {
            foreach (['target', 'actual'] as $kind) {
                foreach ($sourceData[$kind] as $value) {
                    if ($value !== null) { $hasRevenueData = true; break 3; }
                }
            }
        }
        if (!$hasRevenueData) throw new Exception('Nilai kumulatif REVENUE EXIST pada sheet YFR - IBFM dan IAP masih kosong.');
        $hasFoodData = false;
        foreach ([$monthlyFd, $monthlyNewFd] as $foodData) {
            foreach (['target', 'actual'] as $kind) {
                foreach ($foodData[$kind] as $value) {
                    if ($value !== null) { $hasFoodData = true; break 3; }
                }
            }
        }
        $conn->begin_transaction();
        // Upload sheet IAP saja tidak boleh menghapus nilai IB FM yang sudah
        // tersimpan (dan sebaliknya). Nilai NULL mempertahankan nilai lama.
        $insert = $conn->prepare('INSERT INTO `revenue_ibfm` (report_period, month_number, target_ib_fm, actual_ib_fm, target_ib_fd, actual_ib_fd, target_new_ib_fm, actual_new_ib_fm, target_new_ib_fd, actual_new_ib_fd, target_mcm, actual_mcm) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                target_ib_fm = COALESCE(VALUES(target_ib_fm), target_ib_fm),
                actual_ib_fm = COALESCE(VALUES(actual_ib_fm), actual_ib_fm),
                target_ib_fd = COALESCE(VALUES(target_ib_fd), target_ib_fd),
                actual_ib_fd = COALESCE(VALUES(actual_ib_fd), actual_ib_fd),
                target_new_ib_fm = COALESCE(VALUES(target_new_ib_fm), target_new_ib_fm),
                actual_new_ib_fm = COALESCE(VALUES(actual_new_ib_fm), actual_new_ib_fm),
                target_new_ib_fd = COALESCE(VALUES(target_new_ib_fd), target_new_ib_fd),
                actual_new_ib_fd = COALESCE(VALUES(actual_new_ib_fd), actual_new_ib_fd),
                target_mcm = COALESCE(VALUES(target_mcm), target_mcm),
                actual_mcm = COALESCE(VALUES(actual_mcm), actual_mcm)');
        if (!$insert) throw new Exception('Gagal menyiapkan penyimpanan IB FM/IB FD: ' . $conn->error);
        $insertCanonical = $conn->prepare('INSERT INTO `ib_monthly_values` (report_period, month_number, metric_code, business_unit, value_type, amount) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = COALESCE(VALUES(amount), amount)');
        if (!$insertCanonical) throw new Exception('Gagal menyiapkan penyimpanan tabel IB baru: ' . $conn->error);
        for ($month = 1; $month <= 12; $month++) {
            $targetFm = $monthlyFm['target'][$month]; $actualFm = $monthlyFm['actual'][$month];
            $targetFd = $monthlyFd['target'][$month]; $actualFd = $monthlyFd['actual'][$month];
            $targetNewFm = $monthlyNewFm['target'][$month]; $actualNewFm = $monthlyNewFm['actual'][$month];
            $targetNewFd = $monthlyNewFd['target'][$month]; $actualNewFd = $monthlyNewFd['actual'][$month];
            $targetMcm = $monthlyMcm['target'][$month]; $actualMcm = $monthlyMcm['actual'][$month];
            $insert->bind_param('si' . str_repeat('d', 10), $period, $month, $targetFm, $actualFm, $targetFd, $actualFd, $targetNewFm, $actualNewFm, $targetNewFd, $actualNewFd, $targetMcm, $actualMcm);
            if (!$insert->execute()) throw new Exception('Gagal menyimpan data IB FM: ' . $insert->error);
            $canonicalValues = [
                ['revenue_exist', 'IB_FM', 'TARGET', $targetFm], ['revenue_exist', 'IB_FM', 'ACTUAL', $actualFm],
                ['revenue_exist', 'IB_FD', 'TARGET', $targetFd], ['revenue_exist', 'IB_FD', 'ACTUAL', $actualFd],
                ['revenue_new', 'IB_FM', 'TARGET', $targetNewFm], ['revenue_new', 'IB_FM', 'ACTUAL', $actualNewFm],
                ['revenue_new', 'IB_FD', 'TARGET', $targetNewFd], ['revenue_new', 'IB_FD', 'ACTUAL', $actualNewFd],
                ['mcm', 'GROUP', 'TARGET', $targetMcm], ['mcm', 'GROUP', 'ACTUAL', $actualMcm],
            ];
            foreach ($canonicalValues as [$metric, $unit, $valueType, $amount]) {
                $insertCanonical->bind_param('sisssd', $period, $month, $metric, $unit, $valueType, $amount);
                if (!$insertCanonical->execute()) throw new Exception('Gagal menyimpan ke ib_monthly_values: ' . $insertCanonical->error);
            }
        }
        $insert->close(); $insertCanonical->close(); $conn->commit(); $conn->close();
        echo json_encode(['success' => true, 'message' => $hasFoodData
            ? 'REVENUE EXIST, REVENUE NEW, dan MCM berhasil dimuat ke sheet IB.'
            : 'Data IB dan MCM berhasil dimuat; sheet IAP kosong sehingga baris IB FD belum memiliki nilai.',
            'rows_inserted' => 12, 'ib_fd_has_data' => $hasFoodData]);
        exit;
    }

    // ETHICAL disimpan per tahun, produk, bulan, dan TARGET/REAL. Nilai dari
    // file ODS/XLSX masuk ke database; upload ulang mengganti tahun tersebut
    // dalam satu transaksi sehingga tidak menggandakan baris.
    if ($divisi === 'ETHICAL') {
        $spreadsheet = IOFactory::load($file);
        $sheet = null;
        $sourceTitle = '';
        $monthAliases = [
            'JAN' => 1, 'JANUARI' => 1, 'FEB' => 2, 'FEBRUARI' => 2,
            'MAR' => 3, 'MARET' => 3, 'APR' => 4, 'APRIL' => 4,
            'MAY' => 5, 'MEI' => 5, 'JUN' => 6, 'JUNI' => 6,
            'JUL' => 7, 'JULI' => 7, 'AUG' => 8, 'AGS' => 8,
            'AGUSTUS' => 8, 'SEP' => 9, 'SEPTEMBER' => 9,
            'OCT' => 10, 'OKT' => 10, 'OKTOBER' => 10,
            'NOV' => 11, 'NOVEMBER' => 11, 'DEC' => 12,
            'DES' => 12, 'DESEMBER' => 12,
        ];
        $columnLetter = static fn(int $column): string => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
        $number = static function ($value): float {
            if (is_numeric($value)) return (float)$value;
            $value = trim((string)$value);
            if ($value === '') return 0.0;
            $value = preg_replace('/[^0-9,.\-]/', '', $value);
            if (str_contains($value, ',') && str_contains($value, '.')) {
                if (strrpos($value, ',') > strrpos($value, '.')) {
                    $value = str_replace('.', '', $value);
                    $value = str_replace(',', '.', $value);
                } else {
                    $value = str_replace(',', '', $value);
                }
            } elseif (substr_count($value, ',') > 1 || substr_count($value, '.') > 1) {
                $value = str_replace([',', '.'], '', $value);
            } elseif (str_contains($value, ',')) {
                $value = strlen(substr(strrchr($value, ','), 1)) === 3
                    ? str_replace(',', '', $value) : str_replace(',', '.', $value);
            }
            return is_numeric($value) ? (float)$value : 0.0;
        };
        $productSheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
            if (!preg_match('/^PRODUK\s+/i', trim($candidate->getTitle()))) continue;
            $monthColumns = [];
            $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($candidate->getHighestDataColumn());
            for ($column = 1; $column <= $highestColumn; $column++) {
                $label = strtoupper(preg_replace('/[^A-Z]/i', '', trim((string)$candidate->getCell($columnLetter($column) . '1')->getValue())));
                if (isset($monthAliases[$label])) $monthColumns[$monthAliases[$label]] = $column;
            }
            if (count($monthColumns) === 12) $productSheets[] = [$candidate, $monthColumns];
        }

        if ($productSheets) {
            $sourceFilename = (string)($_FILES['file']['name'] ?? '');
            if (!preg_match('/(20\d{2})/', $sourceFilename, $yearMatch)) {
                throw new Exception('Tahun data ETHICAL tidak ditemukan pada nama file raw produk.');
            }
            $sourceYear = (int)$yearMatch[1];
            $sourceTitle = 'TARGET ETHICAL ' . $sourceYear;
            $npTotalLabels = [];
            foreach ($productSheets as [$candidate, $_candidateMonths]) {
                for ($rowNumber = 1; $rowNumber <= $candidate->getHighestDataRow(); $rowNumber++) {
                    $label = strtoupper(trim(preg_replace('/\s+/', ' ', (string)$candidate->getCell('B' . $rowNumber)->getValue())));
                    if (preg_match('/^TOTAL NP(?:\s+(?:20\d{2}|\d{2}))?$/', $label)) $npTotalLabels[$label] = true;
                }
            }
            $npTotalLabels = array_keys($npTotalLabels);
            sort($npTotalLabels, SORT_NATURAL);
            $rawTotals = array_fill_keys(array_merge(['TOTAL EXISTING', 'TOTAL STREAMLINE'], $npTotalLabels), array_fill(1, 12, ['hna' => 0.0, 'hjp_target' => 0.0, 'hjp_real' => 0.0]));
            foreach ($productSheets as [$productSheet, $monthColumns]) {
                for ($month = 1; $month <= 12; $month++) {
                    $base = $monthColumns[$month];
                    if (strtoupper(trim((string)$productSheet->getCell($columnLetter($base) . '2')->getValue())) !== 'TARGET'
                        || strtoupper(trim((string)$productSheet->getCell($columnLetter($base + 3) . '2')->getValue())) !== 'REAL'
                        || strtoupper(trim((string)$productSheet->getCell($columnLetter($base + 1) . '3')->getValue())) !== 'HNA'
                        || strtoupper(trim((string)$productSheet->getCell($columnLetter($base + 2) . '3')->getValue())) !== 'HJP'
                        || strtoupper(trim((string)$productSheet->getCell($columnLetter($base + 4) . '3')->getValue())) !== 'HNA'
                        || strtoupper(trim((string)$productSheet->getCell($columnLetter($base + 5) . '3')->getValue())) !== 'HJP') {
                        throw new Exception('Susunan kolom TARGET/REAL UNIT, HNA, HJP tidak sesuai pada sheet ' . $productSheet->getTitle() . ', bulan ' . $month . '.');
                    }
                    for ($rowNumber = 1; $rowNumber <= $productSheet->getHighestDataRow(); $rowNumber++) {
                        $label = strtoupper(trim(preg_replace('/\s+/', ' ', (string)$productSheet->getCell('B' . $rowNumber)->getValue())));
                        if (!isset($rawTotals[$label])) continue;
                        foreach (['hna' => $base + 1, 'hjp_target' => $base + 2, 'hjp_real' => $base + 5] as $metric => $column) {
                            $cell = $productSheet->getCell($columnLetter($column) . $rowNumber);
                            $value = $cell->getValue();
                            if (is_string($value) && str_starts_with($value, '=')) {
                                try { $value = $cell->getCalculatedValue(); } catch (Throwable $ignored) { $value = null; }
                            }
                            $rawTotals[$label][$month][$metric] += $number($value);
                        }
                    }
                }
            }

            // Ubah total empat sheet produk menjadi struktur Ethical ringkas
            // yang sudah dipakai database/API lama. HNA yang disimpan adalah
            // HNA TARGET; HJP tetap tersedia untuk fitur dashboard Ethical.
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('TARGET ETHICAL');
            $sheet->setCellValue('A1', $sourceTitle);
            $summaryRows = [
                [6, 'EXISTING PRODUCT'], [7, 'DETAIL EXISTING'], [8, 'TOTAL EXISTING PRODUCT'],
                [9, 'STREAMLINE'], [10, 'DETAIL STREAMLINE'], [11, 'TOTAL STREAMLINE'],
            ];
            foreach ($summaryRows as [$row, $label]) $sheet->setCellValue('B' . $row, $label);
            $summaryMapping = [8 => ['TOTAL EXISTING'], 11 => ['TOTAL STREAMLINE']];
            $npYearLabels = [];
            foreach ($npTotalLabels as $label) {
                preg_match('/^TOTAL NP(?:\s+(20\d{2}|\d{2}))?$/', $label, $yearMatch);
                $suffix = $yearMatch[1] ?? '';
                $baseLabel = $suffix === '' ? 'NEW PRODUCT' : 'NEW PRODUCT ' . $suffix;
                $row = 12 + (count($npYearLabels) * 3);
                $totalLabel = $suffix === '' ? 'TOTAL NEW PRODUCT' : 'TOTAL NEW PRODUCT ' . $suffix;
                $summaryRows[] = [$row, $baseLabel];
                $summaryRows[] = [$row + 1, 'DETAIL NP' . ($suffix === '' ? '' : ' ' . $suffix)];
                $summaryRows[] = [$row + 2, $totalLabel];
                $summaryMapping[$row + 2] = [$label];
                $npYearLabels[$label] = true;
            }
            foreach (array_slice($summaryRows, 6) as [$row, $label]) $sheet->setCellValue('B' . $row, $label);
            for ($month = 1; $month <= 12; $month++) {
                $base = 14 + (($month - 1) * 6);
                $sheet->setCellValue($columnLetter($base) . '3', array_search($month, $monthAliases, true));
                foreach ([0 => 'TARGET', 3 => 'REAL'] as $offset => $type) $sheet->setCellValue($columnLetter($base + $offset) . '4', $type);
                foreach ([0 => 'UNIT', 1 => 'HNA', 2 => 'HJP', 3 => 'UNIT', 4 => 'HNA', 5 => 'HJP'] as $offset => $label) {
                    $sheet->setCellValue($columnLetter($base + $offset) . '5', $label);
                }
                foreach ($summaryMapping as $row => $labels) {
                    foreach ($labels as $label) {
                        $sheet->setCellValue($columnLetter($base + 1) . $row, $rawTotals[$label][$month]['hna']);
                        $sheet->setCellValue($columnLetter($base + 2) . $row, $rawTotals[$label][$month]['hjp_target']);
                        $sheet->setCellValue($columnLetter($base + 5) . $row, $rawTotals[$label][$month]['hjp_real']);
                    }
                }
            }

            // Simpan baris produk dari sheet master juga. Sheet EDITED dapat
            // kehilangan hasil rumus, jadi nilai formula dibaca dari workbook
            // sumber yang diunggah; tab produk turunan tidak dijumlahkan ulang.
            $masterSheet = null;
            $masterMonthColumns = null;
            foreach ($productSheets as [$candidate, $candidateMonths]) {
                if (strcasecmp(trim($candidate->getTitle()), 'PRODUK NAS') === 0) {
                    $masterSheet = $candidate;
                    $masterMonthColumns = $candidateMonths;
                    break;
                }
            }
            $rawProductCount = 0;
            if ($masterSheet && $masterMonthColumns) {
                $readRawValue = static function (int $column, int $row) use ($masterSheet, $columnLetter) {
                    $cell = $masterSheet->getCell($columnLetter($column) . $row);
                    $value = $cell->getValue();
                    if (is_string($value) && str_starts_with($value, '=')) {
                        try { $value = $cell->getCalculatedValue(); } catch (Throwable $ignored) { $value = null; }
                    }
                    return $value;
                };
                $normalizedRow = max(array_column($summaryRows, 0)) + 3;
                for ($rawRow = 4; $rawRow <= $masterSheet->getHighestDataRow(); $rawRow++) {
                    $productNo = trim((string)($readRawValue(1, $rawRow) ?? ''));
                    $productName = trim((string)($readRawValue(2, $rawRow) ?? ''));
                    $category = strtoupper(trim((string)($readRawValue(3, $rawRow) ?? '')));
                    if ($productNo === '' || $productName === '' || !preg_match('/^(?:EXISTING|STREAMLINE|NP(?:\s+(?:20\d{2}|\d{2}))?)$/', $category)) continue;

                    $sheet->setCellValue('A' . $normalizedRow, $productNo);
                    $sheet->setCellValue('B' . $normalizedRow, $productName);
                    $sheet->setCellValue('C' . $normalizedRow, $number($readRawValue(4, $rawRow)));
                    foreach (['D', 'E', 'F', 'G'] as $column) $sheet->setCellValue($column . $normalizedRow, 0);
                    for ($month = 1; $month <= 12; $month++) {
                        $sourceBase = $masterMonthColumns[$month];
                        $targetBase = 14 + (($month - 1) * 6);
                        foreach (range(0, 5) as $offset) {
                            $sheet->setCellValue($columnLetter($targetBase + $offset) . $normalizedRow,
                                $number($readRawValue($sourceBase + $offset, $rawRow)));
                        }
                    }
                    $normalizedRow++;
                    $rawProductCount++;
                }
            }
        } else {
            foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
                $title = trim((string)$candidate->getCell('A1')->getValue());
                if (preg_match('/ETHICAL/i', $title) && preg_match('/20\d{2}/', $title)) {
                    $sheet = $candidate;
                    $sourceTitle = $title;
                    break;
                }
            }
        }
        if (!$sheet) {
            $sheet = $spreadsheet->getActiveSheet();
            $sourceTitle = trim((string)$sheet->getCell('A1')->getValue());
        }
        if (!preg_match('/(20\d{2})/', $sourceTitle, $yearMatch)) {
            throw new Exception('Tahun data ETHICAL tidak ditemukan pada judul A1 file.');
        }
        $sourceYear = (int)$yearMatch[1];
        if ($sourceYear < 2025) throw new Exception('Tahun data ETHICAL tidak valid.');

        $readCell = static function (int $column, int $row) use ($sheet, $columnLetter) {
            $cell = $sheet->getCell($columnLetter($column) . $row);
            $value = $cell->getValue();
            if (is_string($value) && str_starts_with($value, '=')) {
                try { $value = $cell->getCalculatedValue(); } catch (Throwable $ignored) { $value = null; }
            }
            return $value;
        };
        $monthColumns = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $highestColumn; $column++) {
            $label = strtoupper(preg_replace('/[^A-Z]/i', '', trim((string)$readCell($column, 3))));
            if (isset($monthAliases[$label])) $monthColumns[$monthAliases[$label]] = $column;
        }
        if (count($monthColumns) !== 12) {
            throw new Exception('Header bulan Januari–Desember tidak lengkap pada baris 3 file ETHICAL.');
        }
        for ($month = 1; $month <= 12; $month++) {
            $base = $monthColumns[$month];
            if (strtoupper(trim((string)$readCell($base, 4))) !== 'TARGET'
                || strtoupper(trim((string)$readCell($base + 3, 4))) !== 'REAL'
                || strtoupper(trim((string)$readCell($base, 5))) !== 'UNIT'
                || strtoupper(trim((string)$readCell($base + 1, 5))) !== 'HNA'
                || strtoupper(trim((string)$readCell($base + 2, 5))) !== 'HJP'
                || strtoupper(trim((string)$readCell($base + 3, 5))) !== 'UNIT'
                || strtoupper(trim((string)$readCell($base + 4, 5))) !== 'HNA'
                || strtoupper(trim((string)$readCell($base + 5, 5))) !== 'HJP') {
                throw new Exception('Susunan kolom TARGET/REAL UNIT, HNA, HJP tidak sesuai untuk bulan ' . $month . '.');
            }
        }

        $insertSql = "INSERT INTO `ETHICAL` (
            report_year, source_row, product_no, product_name, product_hna,
            avg_current_year, avg_previous_year, estimated_total_previous_year, g_value,
            total_target_unit, total_target_hna, total_target_hjp,
            total_real_unit, total_real_hna, total_real_hjp,
            month_number, data_type, unit_value, hna_value, hjp_value, source_filename
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $insert = $conn->prepare($insertSql);
        if (!$insert) throw new Exception('Gagal menyiapkan penyimpanan tabel ETHICAL: ' . $conn->error);
        $bindEthicalRow = static function (mysqli_stmt $statement, array $values): bool {
            $types = 'iiss' . str_repeat('d', 11) . 'is' . str_repeat('d', 3) . 's';
            $arguments = [$types];
            foreach ($values as $index => &$value) $arguments[] = &$value;
            unset($value);
            return call_user_func_array([$statement, 'bind_param'], $arguments);
        };

        $dataRows = [];
        for ($rowNumber = 6; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $productNoValue = $readCell(1, $rowNumber);
            $productNameValue = $readCell(2, $rowNumber);
            $productNo = trim((string)($productNoValue ?? ''));
            $productName = trim((string)($productNameValue ?? ''));
            if ($productNo === '' && $productName === '') continue;
            $dataRows[] = [
                'source_row' => $rowNumber,
                'product_no' => $productNo,
                'product_name' => $productName,
                'metadata' => [
                    $number($readCell(3, $rowNumber)), $number($readCell(4, $rowNumber)),
                    $number($readCell(5, $rowNumber)), $number($readCell(6, $rowNumber)),
                    $number($readCell(7, $rowNumber)),
                ],
                'totals' => array_map(fn(int $column): float => $number($readCell($column, $rowNumber)), range(8, 13)),
            ];
        }
        if (!$dataRows) {
            $insert->close();
            throw new Exception('Tidak ada baris produk ETHICAL yang dapat disimpan dari file.');
        }

        $conn->begin_transaction();
        $deleteYear = $conn->prepare('DELETE FROM `ETHICAL` WHERE report_year = ?');
        if (!$deleteYear) throw new Exception('Gagal menyiapkan penggantian data tahun ETHICAL: ' . $conn->error);
        $deleteYear->bind_param('i', $sourceYear);
        if (!$deleteYear->execute()) throw new Exception('Gagal mengganti data ETHICAL tahun ' . $sourceYear . ': ' . $deleteYear->error);
        $deleteYear->close();

        $inserted = 0;
        foreach ($dataRows as $dataRow) {
            foreach ($monthColumns as $month => $base) {
                foreach ([
                    ['TARGET', 0],
                    ['REAL', 3],
                ] as [$dataType, $offset]) {
                    $values = [
                        $sourceYear, $dataRow['source_row'], $dataRow['product_no'], $dataRow['product_name'],
                        $dataRow['metadata'][0], $dataRow['metadata'][1], $dataRow['metadata'][2],
                        $dataRow['metadata'][3], $dataRow['metadata'][4],
                        $dataRow['totals'][0], $dataRow['totals'][1], $dataRow['totals'][2],
                        $dataRow['totals'][3], $dataRow['totals'][4], $dataRow['totals'][5],
                        $month, $dataType, $number($readCell($base + $offset, $dataRow['source_row'])),
                        $number($readCell($base + $offset + 1, $dataRow['source_row'])),
                        $number($readCell($base + $offset + 2, $dataRow['source_row'])),
                        substr((string)($_FILES['file']['name'] ?? ''), 0, 255),
                    ];
                    if (!$bindEthicalRow($insert, $values) || !$insert->execute()) {
                        throw new Exception('Gagal menyimpan data ETHICAL pada baris ' . $dataRow['source_row'] . ': ' . $insert->error);
                    }
                    $inserted++;
                }
            }
        }
        $insert->close();
        $conn->commit();
        $conn->close();

        echo json_encode([
            'success' => true,
            'message' => 'Data ETHICAL tahun ' . $sourceYear . ' berhasil disimpan ke database.',
            'report_year' => $sourceYear,
            'products' => isset($rawProductCount) && $rawProductCount > 0 ? $rawProductCount : count($dataRows),
            'rows_inserted' => $inserted,
        ]);
        exit;
    }

    // Selling In memakai file ringkasan ODS dan disimpan sebagai subtotal per parameter.
    if ($divisi === 'Revenue Konimex Selling In') {
        $spreadsheet = IOFactory::load($file);
        $sourceSheet = $spreadsheet->getActiveSheet();
        $sourcePeriodLabel = trim((string)$sourceSheet->getCell('P6')->getValue());
        $sourceAmountHeader = trim((string)$sourceSheet->getCell('Q7')->getValue());
        [$periodYear, $periodMonth] = array_map('intval', explode('-', $period));
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $expectedPeriodLabel = 'Bulan Januari ' . $periodYear . ' s.d ' . $monthNames[$periodMonth] . ' ' . $periodYear;
        $periodLabelPattern = '/^Bulan\s+Januari\s+' . $periodYear . '\s+s\.?d\.?\s+'
            . preg_quote($monthNames[$periodMonth], '/') . '\s+' . $periodYear . '$/iu';
        if (!preg_match($periodLabelPattern, $sourcePeriodLabel)
            || strcasecmp($sourceAmountHeader, 'Rupiah') !== 0) {
            throw new Exception('File harus berisi kolom Rupiah untuk periode ' . $expectedPeriodLabel . ' seperti format raw data Selling In.');
        }
        $subtotals = [];
        $excluded = ['09' => true, '13' => true, '16' => true];

        for ($rowNumber = 1; $rowNumber <= $sourceSheet->getHighestDataRow(); $rowNumber++) {
            $label = trim((string)$sourceSheet->getCell('B' . $rowNumber)->getValue());
            if (strcasecmp($label, 'Sub Total') !== 0) continue;

            $group = trim((string)$sourceSheet->getCell('D' . $rowNumber)->getValue());
            if (!preg_match('/^(\d{2})\s*-\s*(.+)$/u', $group, $match)) continue;
            $code = $match[1];
            if (isset($excluded[$code])) continue;

            // Kolom Q berisi Rupiah kumulatif Januari sampai periode yang dipilih.
            $amount = $sourceSheet->getCell('Q' . $rowNumber)->getCalculatedValue();
            if (!is_numeric($amount)) continue;
            $subtotals[$code] = (float)$amount;
        }

        $requiredCodes = ['01', '02', '03', '04', '05', '06', '10', '14', '15'];
        foreach ($requiredCodes as $code) {
            if (!array_key_exists($code, $subtotals)) {
                throw new Exception('Subtotal kode ' . $code . ' tidak ditemukan pada file Selling In (kolom Q: Rupiah ' . $expectedPeriodLabel . ').');
            }
        }

        $reportRows = [
            ['1.', 'KORPORAT', null],
            ['', 'Domestik', null],
            ['1.1', 'Farmasi', null],
            ['', 'GB 1', $subtotals['01']],
            ['', 'GB 2', $subtotals['02']],
            ['', 'GB 3', $subtotals['03']],
            ['', 'GB 4', $subtotals['04']],
            ['1.2', 'Biskuit', $subtotals['05']],
            ['1.3', 'Candy', $subtotals['06']],
            ['1.4', 'Ethical', $subtotals['10']],
            ['2.', 'Ekspor', null],
            ['2.1', 'IB Farma', $subtotals['14']],
            ['2.2', 'IB Food', $subtotals['15']],
        ];
        $farmasi = $subtotals['01'] + $subtotals['02'] + $subtotals['03'] + $subtotals['04'];
        $domestik = $farmasi + $subtotals['05'] + $subtotals['06'] + $subtotals['10'];
        $ekspor = $subtotals['14'] + $subtotals['15'];
        $korporat = $domestik + $ekspor;
        foreach ($reportRows as &$reportRow) {
            if ($reportRow[1] === 'Farmasi') $reportRow[2] = $farmasi;
            if ($reportRow[1] === 'Domestik') $reportRow[2] = $domestik;
            if ($reportRow[1] === 'Ekspor') $reportRow[2] = $ekspor;
            if ($reportRow[1] === 'KORPORAT') $reportRow[2] = $korporat;
        }
        unset($reportRow);

        $conn->begin_transaction();
        $insert = $conn->prepare("INSERT INTO `{$tableName}` (report_period, parameter, achievement) VALUES (?, ?, ?)");
        if (!$insert) throw new Exception('Gagal menyiapkan query Selling In: ' . $conn->error);
        $insert->bind_param('ssd', $period, $parameter, $achievement);
        foreach ($reportRows as $reportRow) {
            if ($reportRow[2] === null) continue;
            $parameter = $reportRow[1];
            $achievement = $reportRow[2];
            if (!$insert->execute()) throw new Exception('Gagal menyimpan ' . $parameter . ': ' . $insert->error);
        }
        $insert->close();

        $conn->commit();
        $conn->close();

        echo json_encode([
            'success' => true,
            'message' => 'Revenue Konimex Selling In berhasil disimpan.',
            'rows_inserted' => count(array_filter($reportRows, static fn($row) => $row[2] !== null))
        ]);
        exit;
    }

    // =========================
    // 2. BACA EXCEL SELLING OUT
    // =========================

    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();

    // Header Excel berada di baris 4
    $header = $sheet->rangeToArray(
        'A4:AE4',
        null,
        true,
        true,
        false
    )[0];

    // =========================
    // 3. CEK HEADER
    // =========================

    $expectedHeader = [
        'GB',
        'PRODUCT CODE',
        'PRINCIPAL PRODUCT CODE',
        'PRODUCT NAME',
        'KATEGORI',
        'QTY JAN',
        'QTY FEB',
        'QTY MAR',
        'QTY APR',
        'QTY MEI',
        'QTY JUN',
        'QTY JUL',
        'QTY AGT',
        'QTY SEP',
        'QTY OKT',
        'QTY NOV',
        'QTY DES',
        'QTY TOTAL',
        'REV JAN',
        'REV FEB',
        'REV MAR',
        'REV APR',
        'REV MEI',
        'REV JUN',
        'REV JUL',
        'REV AGT',
        'REV SEP',
        'REV OKT',
        'REV NOV',
        'REV DES',
        'REV TOTAL'
    ];

    if ($header !== $expectedHeader) {
        throw new Exception(
            'Header Excel tidak sesuai dengan struktur ' . $divisi . '.'
        );
    }

    // =========================
    // 5. SIAPKAN QUERY
    // =========================

    $sql = "
        INSERT INTO `{$tableName}` (
            report_period,
            gb,
            product_code,
            principal_product_code,
            product_name,
            kategori,

            qty_jan,
            qty_feb,
            qty_mar,
            qty_apr,
            qty_mei,
            qty_jun,
            qty_jul,
            qty_agt,
            qty_sep,
            qty_okt,
            qty_nov,
            qty_des,
            qty_total,

            rev_jan,
            rev_feb,
            rev_mar,
            rev_apr,
            rev_mei,
            rev_jun,
            rev_jul,
            rev_agt,
            rev_sep,
            rev_okt,
            rev_nov,
            rev_des,
            rev_total
        )
        VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            'Gagal menyiapkan query: ' . $conn->error
        );
    }
    $unitStmt = null;
    if ($divisi === 'REVENUE VERSI EDP MNJ') {
        $unitStmt = $conn->prepare("INSERT INTO `UNIT` (
            report_period, gb, product_code, principal_product_code, product_name, kategori,
            unit_jan, unit_feb, unit_mar, unit_apr, unit_mei, unit_jun, unit_jul, unit_agt,
            unit_sep, unit_okt, unit_nov, unit_des, unit_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$unitStmt) throw new Exception('Gagal menyiapkan query UNIT: ' . $conn->error);
    }

    // =========================
    // 6. TRANSACTION
    // =========================

    $conn->begin_transaction();

    if ($existingRows > 0 && $replaceExistingPeriod) {
        $deletePeriod = $conn->prepare("DELETE FROM `{$tableName}` WHERE report_period = ?");
        if (!$deletePeriod) {
            throw new Exception('Gagal menyiapkan penggantian data periode: ' . $conn->error);
        }
        $deletePeriod->bind_param('s', $period);
        if (!$deletePeriod->execute()) {
            throw new Exception('Gagal mengganti data periode: ' . $deletePeriod->error);
        }
        $deletePeriod->close();
        if ($unitStmt) {
            $deleteUnit = $conn->prepare('DELETE FROM `UNIT` WHERE report_period = ?');
            if (!$deleteUnit) throw new Exception('Gagal menyiapkan penggantian periode UNIT: ' . $conn->error);
            $deleteUnit->bind_param('s', $period);
            if (!$deleteUnit->execute()) throw new Exception('Gagal mengganti data UNIT: ' . $deleteUnit->error);
            $deleteUnit->close();
        }
    }

    $inserted = 0;

    $highestRow = $sheet->getHighestRow();

    // Data mulai dari baris 5
    for ($rowNumber = 5; $rowNumber <= $highestRow; $rowNumber++) {

        $row = $sheet->rangeToArray(
            'A' . $rowNumber . ':AE' . $rowNumber,
            null,
            true,
            false,
            false
        )[0];

        // Lewati baris kosong
        $hasData = false;

        foreach ($row as $value) {
            if ($value !== null && trim((string)$value) !== '') {
                $hasData = true;
                break;
            }
        }

        if (!$hasData) {
            continue;
        }

        // =========================
        // DATA TEKS
        // =========================

        $gb = trim((string)$row[0]);
        $productCode = trim((string)$row[1]);
        $principalProductCode = trim((string)$row[2]);
        $productName = trim((string)$row[3]);
        $kategori = trim((string)$row[4]);

        // =========================
        // DATA ANGKA
        // =========================

        $numbers = [];

        for ($i = 5; $i < 31; $i++) {

            $value = $row[$i];

            if ($value === null || $value === '') {
                $numbers[] = 0;
            } else {
                $numbers[] = (float)$value;
            }
        }
        $unitNumbers = array_slice($numbers, 0, 13);
        // Pada jalur upload UNIT & Revenue, nilai QTY hanya masuk ke tabel UNIT.
        if ($unitStmt) {
            for ($i = 0; $i < 13; $i++) $numbers[$i] = 0;
        }

        // =========================
        // BIND DATA
        // =========================

        $stmt->bind_param(
            'ssssss' . str_repeat('d', 26),
            $period,
            $gb,
            $productCode,
            $principalProductCode,
            $productName,
            $kategori,

            $numbers[0],
            $numbers[1],
            $numbers[2],
            $numbers[3],
            $numbers[4],
            $numbers[5],
            $numbers[6],
            $numbers[7],
            $numbers[8],
            $numbers[9],
            $numbers[10],
            $numbers[11],
            $numbers[12],

            $numbers[13],
            $numbers[14],
            $numbers[15],
            $numbers[16],
            $numbers[17],
            $numbers[18],
            $numbers[19],
            $numbers[20],
            $numbers[21],
            $numbers[22],
            $numbers[23],
            $numbers[24],
            $numbers[25]
        );

        if (!$stmt->execute()) {
            throw new Exception(
                'Gagal memasukkan baris Excel ke-' .
                $rowNumber .
                ': ' .
                $stmt->error
            );
        }
        if ($unitStmt) {
            $unitStmt->bind_param('ssssss' . str_repeat('d', 13),
                $period, $gb, $productCode, $principalProductCode, $productName, $kategori,
                $unitNumbers[0], $unitNumbers[1], $unitNumbers[2], $unitNumbers[3], $unitNumbers[4], $unitNumbers[5], $unitNumbers[6],
                $unitNumbers[7], $unitNumbers[8], $unitNumbers[9], $unitNumbers[10], $unitNumbers[11], $unitNumbers[12]);
            if (!$unitStmt->execute()) throw new Exception('Gagal menyimpan UNIT pada baris Excel ke-' . $rowNumber . ': ' . $unitStmt->error);
        }

        $inserted++;
    }

    // =========================
    // 7. SIMPAN TRANSACTION
    // =========================

    $conn->commit();

    $stmt->close();
    if ($unitStmt) $unitStmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Excel berhasil diimport ke database.',
        'rows_inserted' => $inserted
    ]);

} catch (Throwable $e) {

    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }

        $conn->close();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
