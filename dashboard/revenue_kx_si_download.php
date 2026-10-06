<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$period = trim((string)($_GET['period'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Periode harus berformat YYYY-MM.');
}

$conn = new mysqli('127.0.0.1', 'root', '', 'LAPORAN KORPORAT');
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Database gagal terhubung: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare('SELECT parameter, achievement FROM revenue_konimex_selling_in WHERE report_period = ?');
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Query unduh gagal: ' . $conn->error);
}
$stmt->bind_param('s', $period);
$stmt->execute();
$result = $stmt->get_result();
$amounts = [];
while ($row = $result->fetch_assoc()) $amounts[$row['parameter']] = (float)$row['achievement'];
$stmt->close();
$conn->close();

if (!$amounts) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Belum ada data Revenue Konimex Selling In untuk periode ' . $period . '.');
}

$definitions = [
    ['1.', 'KORPORAT', ['Domestik', 'Ekspor']],
    ['', 'Domestik', ['Farmasi', 'Biskuit', 'Candy', 'Ethical']],
    ['1.1', 'Farmasi', ['GB 1', 'GB 2', 'GB 3', 'GB 4']],
    ['', 'GB 1', []],
    ['', 'GB 2', []],
    ['', 'GB 3', []],
    ['', 'GB 4', []],
    ['1.2', 'Biskuit', []],
    ['1.3', 'Candy', []],
    ['1.4', 'Ethical', []],
    ['2.', 'Ekspor', ['IB Farma', 'IB Food']],
    ['2.1', 'IB Farma', []],
    ['2.2', 'IB Food', []],
];

$output = new Spreadsheet();
$sheet = $output->getActiveSheet();
$sheet->setTitle('Revenue KX Selling In');
$sheet->mergeCells('A1:A2');
$sheet->mergeCells('B1:B2');
$sheet->mergeCells('C1:D1');
$sheet->mergeCells('E1:E2');
$sheet->mergeCells('F1:F2');
$sheet->mergeCells('G1:G2');
$sheet->mergeCells('H1:H2');
$sheet->setCellValue('A1', 'No');
$sheet->setCellValue('B1', 'Parameter');
$sheet->setCellValue('C1', 'Ach Last Year (2025)');
$sheet->setCellValue('C2', 'Full Year');
$sheet->setCellValue('D2', 'YTD AGUSTUS');
$sheet->setCellValue('E1', 'Target 2026');
$sheet->setCellValue('F1', "Ach 2026\nYTD AGUSTUS");
$sheet->setCellValue('G1', "% Achievement\n2026");
$sheet->setCellValue('H1', "Growth\nYTD AGUSTUS");
$sheet->getStyle('A1:H2')->getFont()->setBold(true);
$sheet->getStyle('A1:H2')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
$sheet->getStyle('A1:H2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('91C9F7');

$rowNumber = 3;
$sumAmounts = static function (array $parameters) use ($amounts): float {
    $total = 0.0;
    foreach ($parameters as $parameter) {
        $total += $amounts[$parameter] ?? 0.0;
    }
    return $total;
};
$farmasiTotal = $sumAmounts(['GB 1', 'GB 2', 'GB 3', 'GB 4']);
$domestikTotal = $farmasiTotal + $sumAmounts(['Biskuit', 'Candy', 'Ethical']);
$eksporTotal = $amounts['Ekspor'] ?? $sumAmounts(['IB Farma', 'IB Food']);
$calculatedAmounts = [
    'Farmasi' => $farmasiTotal,
    'Domestik' => $domestikTotal,
    'Ekspor' => $eksporTotal,
    'KORPORAT' => $domestikTotal + $eksporTotal,
];
foreach ($definitions as [$number, $parameter, $children]) {
    $value = $amounts[$parameter] ?? null;

    // Nilai Farmasi dan Domestik selalu dihitung dari rincian, meskipun
    // tabel sumber juga memiliki baris ringkasan dengan nama parameter itu.
    if ($parameter === 'Farmasi') {
        $value = 0.0;
        foreach (['GB 1', 'GB 2', 'GB 3', 'GB 4'] as $child) {
            $value += $amounts[$child] ?? 0.0;
        }
    } elseif ($parameter === 'Domestik') {
        $value = $domestikTotal;
    } elseif ($parameter === 'KORPORAT') {
        $value = $domestikTotal + $eksporTotal;
    } elseif ($value === null && $children) {
        $value = 0.0;
        foreach ($children as $child) {
            $value += $calculatedAmounts[$child] ?? $amounts[$child] ?? 0.0;
        }
    }
    if ($value !== null) $calculatedAmounts[$parameter] = $value;
    $sheet->setCellValue('A' . $rowNumber, $number);
    $sheet->setCellValue('B' . $rowNumber, $parameter);
    if ($value !== null) $sheet->setCellValue('F' . $rowNumber, $value);
    if (in_array($parameter, ['KORPORAT', 'Domestik', 'Ekspor'], true)) {
        $sheet->getStyle('A' . $rowNumber . ':H' . $rowNumber)->getFont()->setBold(true);
    }
    $rowNumber++;
}
$lastRow = $rowNumber - 1;
$sheet->getStyle('A1:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('F3:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);-');
$sheet->getStyle('A3:A' . $lastRow)->getAlignment()->setHorizontal('right');
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(32);
foreach (range('C', 'H') as $column) $sheet->getColumnDimension($column)->setWidth(20);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getRowDimension(2)->setRowHeight(24);
$sheet->freezePane('C3');

$filename = 'Revenue_Konimex_Selling_In_' . str_replace('-', '_', $period) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
(new Xlsx($output))->save('php://output');
