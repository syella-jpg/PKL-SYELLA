<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

function processRevenueExistIbFmSpreadsheet(string $filePath, ?int $expectedYear = null, string $sheetName = 'YFR - IBFM', bool $allowEmpty = false, string $subKpi = 'SALES REVENUE (USD)', int $targetRow = 6): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = null;
    $sheetAliases = strtoupper(trim($sheetName)) === 'YFR - IBFM' || strtoupper(trim($sheetName)) === 'IBFM'
        ? ['YFR - IBFM', 'IBFM']
        : [$sheetName];
    foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
        if (in_array(strtoupper(trim($candidate->getTitle())), array_map('strtoupper', $sheetAliases), true)) {
            $sheet = $candidate;
            break;
        }
    }
    if (!$sheet) throw new RuntimeException('Sheet ' . implode(' atau ', $sheetAliases) . ' tidak ditemukan pada file IB.');
    $title = strtoupper(trim((string)$sheet->getCell('A1')->getValue()));
    if ($expectedYear !== null && preg_match('/\b(20\d{2})\b/', $title, $match) && (int)$match[1] !== $expectedYear) {
        throw new RuntimeException('Tahun pada sheet ' . $sheetName . ' tidak sama dengan periode upload yang dipilih.');
    }
    $normalize = static function ($value): string {
        $value = str_replace(['–', '—'], '-', (string)$value);
        return strtoupper(trim(preg_replace('/\s+/u', ' ', $value)));
    };
    $headers = ['JAN','S/D FEB','S/D MAR','S/D APR','S/D MEI','S/D JUN','S/D JUL','S/D AGS','S/D SEP','S/D OKT','S/D NOV','S/D DES'];
    $monthStartColumn = null;
    $monthHeaderRow = null;
    for ($row = 1; $row <= min(10, $sheet->getHighestRow()) && $monthStartColumn === null; $row++) {
        for ($start = 1; $start <= 30; $start++) {
            $valid = true;
            foreach ($headers as $i => $expected) {
                $column = Coordinate::stringFromColumnIndex($start + $i);
                if ($normalize($sheet->getCell($column . $row)->getValue()) !== $expected) { $valid = false; break; }
            }
            if ($valid) { $monthStartColumn = $start; $monthHeaderRow = $row; break; }
        }
    }
    if ($monthStartColumn === null) throw new RuntimeException('Header bulan JAN–DES tidak ditemukan pada sheet ' . $sheetName . '.');
    $targetColumn = Coordinate::stringFromColumnIndex($monthStartColumn - 1);
    $metricMatches = static function (string $rowLabel) use ($normalize, $subKpi): bool {
        $label = $normalize($rowLabel);
        if (str_contains(strtoupper($subKpi), 'MCM')) {
            return str_contains($label, 'MCM') && str_contains($label, 'REVENUE')
                && str_contains($label, 'HPP') && str_contains($label, 'EXPENSES');
        }
        return str_contains($label, $normalize($subKpi));
    };
    $resolvedTargetRow = null;
    for ($row = 1; $row < min(100, $sheet->getHighestRow()); $row++) {
        $labels = [];
        foreach (['B', 'C'] as $labelColumn) $labels[] = $sheet->getCell($labelColumn . $row)->getValue();
        if ($metricMatches(implode(' ', array_filter($labels, static fn($value) => is_string($value))))
            && $normalize($sheet->getCell($targetColumn . $row)->getValue()) === 'TARGET'
            && $normalize($sheet->getCell($targetColumn . ($row + 1))->getValue()) === 'REALISASI') {
            $resolvedTargetRow = $row;
            break;
        }
    }
    if ($resolvedTargetRow === null) throw new RuntimeException('Struktur ' . $subKpi . ' tidak ditemukan pada sheet ' . $sheet->getTitle() . '.');
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
    foreach ([$resolvedTargetRow => 'target', ($resolvedTargetRow + 1) => 'actual'] as $row => $key) {
        $previous = null;
        for ($month = 1; $month <= 12; $month++) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($monthStartColumn + $month - 1) . $row);
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
