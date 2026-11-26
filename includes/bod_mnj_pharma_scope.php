<?php
/** Return only promotion-cost headers and GB 1/4 values to the Pharma client. */
function bodMnjPharmaRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
{
    $rows = [];
    $active = false;
    for ($r = 1; $r <= $sheet->getHighestDataRow(); $r++) {
        $label = strtoupper(trim((string)$sheet->getCell('B'.$r)->getValue()));
        $heading = strtoupper(trim((string)$sheet->getCell('A'.$r)->getValue()));
        if (preg_match('/^BIAYA PROMOSI MNJ TH /', $heading.' '.$label)) $active = true;
        elseif (preg_match('/^(?:SALES MNJ TH|BIAYA NON PRODUCT)/', $heading.' '.$label) || preg_match('/^(?:SALES MNJ TH|BIAYA NON PRODUCT)/', $label)) $active = false;
        $values = [];
        for ($c = 1; $c <= 18; $c++) $values[] = $sheet->getCell([$c, $r])->getCalculatedValue();
        if (preg_match('/^BIAYA PROMOSI MNJ TH /', $label) || preg_match('/^BIAYA PROMOSI MNJ TH /', $heading)) {
            $active = true;
            $rows[] = array_map(static fn($v) => is_string($v) ? $v : '', $values);
        } elseif ($active && preg_match('/^GB\s*([14])(?:\D|$)/', $label)) {
            $rows[] = $values;
        } elseif ($active && in_array('TARGET', array_map(static fn($v) => strtoupper(trim((string)$v)), $values), true)) {
            $rows[] = array_map(static fn($v) => is_string($v) ? $v : '', $values);
        }
    }
    return $rows;
}
