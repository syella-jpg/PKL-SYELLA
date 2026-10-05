<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'LAPORAN KORPORAT';

try {

    // =========================
    // 1. CEK FILE
    // =========================

    if (!isset($_FILES['file'])) {
        throw new Exception('File Excel belum dikirim.');
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(
            'Upload file gagal. Error code: ' . $_FILES['file']['error']
        );
    }

    $file = $_FILES['file']['tmp_name'];

    // =========================
    // 2. BACA EXCEL
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
            'Header Excel tidak sesuai dengan struktur Revenue Selling Out.'
        );
    }

    // =========================
    // 4. KONEKSI DATABASE
    // =========================

    $conn = new mysqli(
        $host,
        $user,
        $password,
        $database
    );

    if ($conn->connect_error) {
        throw new Exception(
            'Database gagal terhubung: ' . $conn->connect_error
        );
    }

    $conn->set_charset('utf8mb4');

    // =========================
    // 5. SIAPKAN QUERY
    // =========================

    $sql = "
        INSERT INTO revenue_selling_out (
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
            ?, ?, ?, ?, ?,
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
            'sssss' . str_repeat('d', 26),
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