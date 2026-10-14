<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

function processRevenueExistIbFmSpreadsheet(string $filePath, ?int $expectedYear = null, string $sheetName = 'YFR - IBFM', bool $allowEmpty = false, string $subKpi = 'SALES REVENUE (USD)', int $targetRow = 6): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName($sheetName);
    if (!$sheet) throw new RuntimeException('Sheet ' . $sheetName . ' tidak ditemukan pada file IB.');
    $title = strtoupper(trim((string)$sheet->getCell('A1')->getValue()));
    if ($expectedYear !== null && preg_match('/\b(20\d{2})\b/', $title, $match) && (int)$match[1] !== $expectedYear) {
        throw new RuntimeException('Tahun pada sheet ' . $sheetName . ' tidak sama dengan periode upload yang dipilih.');
    }
    if (strtoupper(trim((string)$sheet->getCell('C' . $targetRow)->getValue())) !== strtoupper($subKpi)
        || strtoupper(trim((string)$sheet->getCell('M' . $targetRow)->getValue())) !== 'TARGET'
        || strtoupper(trim((string)$sheet->getCell('M' . ($targetRow + 1))->getValue())) !== 'REALISASI') {
        throw new RuntimeException('Struktur ' . $subKpi . ' di sheet ' . $sheetName . ' tidak sesuai.');
    }
    $headers = ['JAN','S/D FEB','S/D MAR','S/D APR','S/D MEI','S/D JUN','S/D JUL','S/D AGS','S/D SEP','S/D OKT','S/D NOV','S/D DES'];
    foreach ($headers as $i => $expected) {
        $column = Coordinate::stringFromColumnIndex(14 + $i);
        if (strtoupper(trim((string)$sheet->getCell($column . '5')->getValue())) !== $expected) {
            throw new RuntimeException('Header bulan ' . $sheetName . ' tidak sesuai di kolom ' . $column . '.');
        }
    }
    $parse = static function ($value): ?float {
        if ($value === null || trim((string)$value) === '') return null;
        if (is_numeric($value)) return (float)$value;
        $text = preg_replace('/[^0-9,.-]/', '', trim((string)$value));
        if ($text === '' || $text === '-') return null;
        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = strrpos($text, ',') > strrpos($text, '.') ? str_replace(',', '.', str_replace('.', '', $text)) : str_replace(',', '', $text);
        } elseif (str_contains($text, ',')) $text = str_replace(',', '.', $text);
        return is_numeric($text) ? (float)$text : null;
    };
    $result = ['target' => array_fill(1, 12, null), 'actual' => array_fill(1, 12, null)];
    $found = false;
    foreach ([$targetRow => 'target', ($targetRow + 1) => 'actual'] as $row => $key) {
        $previous = null;
        for ($month = 1; $month <= 12; $month++) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex(13 + $month) . $row);
            $raw = $cell->getValue();
            if (is_string($raw) && str_starts_with($raw, '=')) $raw = $cell->getCalculatedValue();
            $cumulative = $parse($raw);
            if ($cumulative !== null) $found = true;
            $result[$key][$month] = $month === 1 ? $cumulative : (($cumulative !== null && $previous !== null) ? $cumulative - $previous : null);
            $previous = $cumulative;
        }
    }
    if (!$found && !$allowEmpty) throw new RuntimeException('Nilai kumulatif TARGET/REALISASI ' . $subKpi . ' di sheet ' . $sheetName . ' masih kosong.');
    return $result;
}
