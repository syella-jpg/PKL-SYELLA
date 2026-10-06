<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') {
        header('Access-Control-Allow-Private-Network: true');
    }
} elseif ($origin === 'http://127.0.0.1:8000' || $origin === 'http://localhost:8000') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$period = trim((string)($_GET['period'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Periode harus berformat YYYY-MM.');
}
$periodYear = (int)substr($period, 0, 4);
$periodMonth = (int)substr($period, 5, 2);
$monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periodMonthName = strtoupper($monthNames[$periodMonth - 1]);

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

$lastYearFullPeriod = ($periodYear - 1) . '-12';
$lastYearYtdPeriod = ($periodYear - 1) . '-' . str_pad((string)$periodMonth, 2, '0', STR_PAD_LEFT);
$loadAmounts = static function (mysqli_stmt $statement, string $requestedPeriod): array {
    $statement->bind_param('s', $requestedPeriod);
    $statement->execute();
    $queryResult = $statement->get_result();
    $values = [];
    while ($row = $queryResult->fetch_assoc()) $values[$row['parameter']] = (float)$row['achievement'];
    return $values;
};
$lastYearFullAmounts = $loadAmounts($stmt, $lastYearFullPeriod);
$lastYearYtdAmounts = $loadAmounts($stmt, $lastYearYtdPeriod);
$stmt->close();
$conn->close();

if (!$amounts) {
    if (($_GET['check'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['available' => false]);
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Belum ada data Revenue Konimex Selling In untuk periode ' . $period . '.');
}

if (($_GET['check'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['available' => true]);
    exit;
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
$sheet->setCellValue('C1', 'Ach Last Year (' . ($periodYear - 1) . ')');
$sheet->setCellValue('C2', 'Full Year');
$sheet->setCellValue('D2', 'YTD ' . $periodMonthName);
$sheet->setCellValue('E1', 'Target ' . $periodYear);
$sheet->setCellValue('F1', "Ach " . $periodYear . "\nYTD " . $periodMonthName);
$sheet->setCellValue('G1', "% Achievement\n" . $periodYear);
$sheet->setCellValue('H1', "Growth\nYTD " . $periodMonthName);
$sheet->getStyle('A1:H2')->getFont()->setBold(true);
$sheet->getStyle('A1:H2')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
$sheet->getStyle('A1:H2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('91C9F7');

$rowNumber = 3;
$resolveAmounts = static function (array $sourceAmounts) use ($definitions): array {
    $sumAmounts = static function (array $parameters) use ($sourceAmounts): float {
        $total = 0.0;
        foreach ($parameters as $parameter) $total += $sourceAmounts[$parameter] ?? 0.0;
        return $total;
    };
    $farmasiTotal = $sumAmounts(['GB 1', 'GB 2', 'GB 3', 'GB 4']);
    $domestikTotal = $farmasiTotal + $sumAmounts(['Biskuit', 'Candy', 'Ethical']);
    $eksporTotal = $sourceAmounts['Ekspor'] ?? $sumAmounts(['IB Farma', 'IB Food']);
    $resolved = [
        'Farmasi' => $farmasiTotal,
        'Domestik' => $domestikTotal,
        'Ekspor' => $eksporTotal,
        'KORPORAT' => $domestikTotal + $eksporTotal,
    ];
    foreach ($definitions as [, $parameter, $children]) {
        $value = $sourceAmounts[$parameter] ?? null;
        if ($parameter === 'Farmasi') {
            $value = $farmasiTotal;
        } elseif ($parameter === 'Domestik') {
            $value = $domestikTotal;
        } elseif ($parameter === 'KORPORAT') {
            $value = $domestikTotal + $eksporTotal;
        } elseif ($value === null && $children) {
            $value = 0.0;
            foreach ($children as $child) $value += $resolved[$child] ?? $sourceAmounts[$child] ?? 0.0;
        }
        if ($value !== null) $resolved[$parameter] = $value;
    }
    return $resolved;
};
$calculatedAmounts = $resolveAmounts($amounts);
$lastYearFullCalculated = $lastYearFullAmounts ? $resolveAmounts($lastYearFullAmounts) : [];
$lastYearYtdCalculated = $lastYearYtdAmounts ? $resolveAmounts($lastYearYtdAmounts) : [];
foreach ($definitions as [$number, $parameter, $children]) {
    $value = $calculatedAmounts[$parameter] ?? null;
    $sheet->setCellValue('A' . $rowNumber, $number);
    $sheet->setCellValue('B' . $rowNumber, $parameter);
    if (array_key_exists($parameter, $lastYearFullCalculated)) $sheet->setCellValue('C' . $rowNumber, $lastYearFullCalculated[$parameter]);
    if (array_key_exists($parameter, $lastYearYtdCalculated)) $sheet->setCellValue('D' . $rowNumber, $lastYearYtdCalculated[$parameter]);
    if ($value !== null) $sheet->setCellValue('F' . $rowNumber, $value);
    if ($value !== null && isset($lastYearYtdCalculated[$parameter]) && $lastYearYtdCalculated[$parameter] != 0.0) {
        $sheet->setCellValue('H' . $rowNumber, '=F' . $rowNumber . '/D' . $rowNumber . '-1');
    }
    if (in_array($parameter, ['KORPORAT', 'Domestik', 'Ekspor'], true)) {
        $sheet->getStyle('A' . $rowNumber . ':H' . $rowNumber)->getFont()->setBold(true);
    }
    $rowNumber++;
}
$lastRow = $rowNumber - 1;
$sheet->getStyle('A1:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('C3:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);-');
$sheet->getStyle('H3:H' . $lastRow)->getNumberFormat()->setFormatCode('0.00%;[Red](0.00%);-');
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
