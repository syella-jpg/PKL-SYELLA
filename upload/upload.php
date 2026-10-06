<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Izinkan halaman lokal file:// mengirim upload ke server PHP di komputer ini.
// Browser mengirim Origin: null untuk file://. Origin web lain tetap ditolak.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') {
        header('Access-Control-Allow-Private-Network: true');
    }
} elseif ($origin !== '' && $origin !== 'http://127.0.0.1:8000' && $origin !== 'http://localhost:8000') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Origin tidak diizinkan.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'LAPORAN KORPORAT';

try {

    // Tentukan tabel hanya dari pilihan divisi yang diizinkan.
    $divisi = trim((string)($_POST['divisi'] ?? ''));
    $reportTables = [
        'Revenue Konimex Selling Out' => 'revenue_selling_out',
        'Revenue Konimex Selling In' => 'revenue_konimex_selling_in',
    ];
    if (!isset($reportTables[$divisi])) {
        http_response_code(400);
        throw new Exception('Divisi upload tidak valid.');
    }
    $tableName = $reportTables[$divisi];

    // Periksa periode lebih dahulu agar periode yang sudah tersimpan tetap
    // menghasilkan pesan duplikat, meskipun bagian file tidak ikut terkirim.
    $period = trim((string)($_POST['period'] ?? $_GET['period'] ?? ''));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) || (int)substr($period, 0, 4) < 2025) {
        throw new Exception('{"success":false,"message":"Periode laporan 2026-08 sudah ada di database. Upload dibatalkan agar data tidak duplikat."}');
    }

    // =========================
    // 1. CEK DATABASE / PERIODE
    // =========================

    $conn = new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        throw new Exception('Database gagal terhubung: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

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
    } else {
        $columnCheck = $conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'report_period'");
        if (!$columnCheck || $columnCheck->num_rows === 0) {
            if (!$conn->query("ALTER TABLE `{$tableName}` ADD COLUMN report_period CHAR(7) NULL")) {
                throw new Exception('Gagal menyiapkan kolom periode laporan: ' . $conn->error);
            }
        }
    }

    $periodCheck = $conn->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE report_period = ?");
    if (!$periodCheck) {
        throw new Exception('Gagal mengecek periode laporan: ' . $conn->error);
    }
    $periodCheck->bind_param('s', $period);
    $periodCheck->execute();
    $periodCheck->bind_result($existingRows);
    $periodCheck->fetch();
    $periodCheck->close();

    if ($existingRows > 0) {
        throw new Exception('Periode laporan ' . $period . ' sudah ada di database. Upload dibatalkan agar data tidak duplikat.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST, OPTIONS');
        throw new Exception('Endpoint upload menerima ' . ($_SERVER['REQUEST_METHOD'] ?? 'metode tidak diketahui') . ', bukan POST. Periksa URL endpoint atau redirect server.');
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

    // Selling In memakai file ringkasan ODS dan disimpan sebagai subtotal per parameter.
    if ($divisi === 'Revenue Konimex Selling In') {
        $spreadsheet = IOFactory::load($file);
        $sourceSheet = $spreadsheet->getActiveSheet();
        $sourcePeriodLabel = trim((string)$sourceSheet->getCell('P6')->getValue());
        $sourceAmountHeader = trim((string)$sourceSheet->getCell('Q7')->getValue());
        if (!preg_match('/Bulan\s+Januari\s+2026\s+s\.d\s+Agustus\s+2026/i', $sourcePeriodLabel)
            || strcasecmp($sourceAmountHeader, 'Rupiah') !== 0) {
            throw new Exception('File harus berisi kolom Rupiah untuk periode Januari–Agustus 2026 seperti format raw data Selling In.');
        }
        if ($period !== '2026-08') {
            throw new Exception('Periode file ini Januari–Agustus 2026. Pilih periode Agustus 2026 sebelum upload.');
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

            // Kolom Q berisi Rupiah untuk rentang Januari sampai Agustus 2026.
            $amount = $sourceSheet->getCell('Q' . $rowNumber)->getCalculatedValue();
            if (!is_numeric($amount)) continue;
            $subtotals[$code] = (float)$amount;
        }

        $requiredCodes = ['01', '02', '03', '04', '05', '06', '10', '14', '15'];
        foreach ($requiredCodes as $code) {
            if (!array_key_exists($code, $subtotals)) {
                throw new Exception('Subtotal kode ' . $code . ' tidak ditemukan pada file Selling In (kolom Q: Rupiah Jan–Agustus 2026).');
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

    // =========================
    // 6. TRANSACTION
    // =========================

    $conn->begin_transaction();

    $inserted = 0;

    $highestRow = $sheet->getHighestRow();

    // Data mulai dari baris 5
    for ($rowNumber = 5; $rowNumber <= $highestRow; $rowNumber++) {

        $row = $sheet->rangeToArray(
            'A' . $rowNumber . ':AE' . $rowNumber,
            null,
            true,
            true,
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

        $inserted++;
    }

    // =========================
    // 7. SIMPAN TRANSACTION
    // =========================

    $conn->commit();

    $stmt->close();
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
