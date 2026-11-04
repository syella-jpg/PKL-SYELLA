<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Parse the compact template and the existing KPI workbook without database access. */
function parseMtmWorkbook(Spreadsheet $spreadsheet): array
{
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
                    if (preg_match('/\bTARGET\s+ROLLING\b|\bT\.\s*ROLLING\b/', $labels)) $typeRows['ROLLING'] = $row;
                    elseif (preg_match('/\bTARGET\b|\bT\.\s*AWAL\b/', $labels)) $typeRows['TARGET'] = $row;
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

    $hasValue = false;
    $meta = $spreadsheet->getSheetByName('_TEMPLATE');
    $isAutoTotalTemplate = $meta && $meta->getCell('A5')->getValue() === 'MTM_TARGETS_V2';
    $inputMetrics = $isAutoTotalTemplate ? array_intersect_key($mtmData, array_flip(['EXISTING','NEW_PRODUCT'])) : $mtmData;
    foreach ($inputMetrics as $types) foreach ($types as $months) foreach ($months as $value) {
        if ($value !== null) $hasValue = true;
    }
    if (!$hasValue) throw new InvalidArgumentException('Template MTM masih kosong. Isi minimal satu nilai Target atau Aktual sebelum upload.');
    return $mtmData;
}
