<?php
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Follow subtotal/rolling references so =F4 over an empty F4 stays blank. */
function biayaHasNumericSource(Worksheet $sheet, string $address, array $visited = []): bool
{
    if (isset($visited[$address])) return false;
    $visited[$address] = true;
    $cell = $sheet->getCell($address);
    $raw = $cell->getValue();
    if (!$cell->isFormula()) return is_numeric($raw);
    $cached = $cell->getOldCalculatedValue();
    if (is_numeric($cached) && (float)$cached !== 0.0) return true;
    preg_match_all('/(?<![A-Z0-9_])(\$?[A-Z]{1,3}\$?[1-9]\d*)(?::(\$?[A-Z]{1,3}\$?[1-9]\d*))?\b/', $raw, $refs, PREG_SET_ORDER);
    if (!$refs) return is_numeric($cached);
    foreach ($refs as $ref) {
        $first = str_replace('$', '', $ref[1]);
        $last = str_replace('$', '', $ref[2] ?? $ref[1]);
        foreach (Coordinate::extractAllCellReferencesInRange($first . ':' . $last) as $source) {
            if (biayaHasNumericSource($sheet, $source, $visited)) return true;
        }
    }
    return false;
}

function parseBiayaWorkbook(\PhpOffice\PhpSpreadsheet\Spreadsheet $book, int $year): array
{
    $rows = [];
    foreach ($book->getAllSheets() as $sheet) {
        if ((int)$sheet->getCell('E3')->getValue() !== $year) continue;
        $rollingHeading = strtoupper(trim((string)$sheet->getCell('X1')->getValue()));
        $hasRolling = str_contains($rollingHeading, 'TARGET ROLLING');
        // The 2025 source has three blocks. Its original target must never
        // substitute for the requested rolling target, even if rolling is empty.
        if ($year === 2025 && !$hasRolling) {
            throw new InvalidArgumentException('File BIAYA 2025 harus memiliki blok TARGET ROLLING. Target awal tidak digunakan.');
        }
        $layout = $hasRolling
            ? ['target' => [20, 23, 24], 'actual' => [38, 40, 42]]
            : ['target' => [1, 4, 6], 'actual' => [20, 22, 23]];
        $monthHeaders = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        foreach ($layout as [$groupCol, $labelCol, $startCol]) {
            foreach ($monthHeaders as $index => $expected) {
                $header = strtoupper(trim((string)$sheet->getCell([$startCol + $index, 3])->getValue()));
                if ($header !== $expected) throw new InvalidArgumentException('Kolom Januari–Desember pada blok biaya tidak sesuai.');
            }
        }
        foreach ($layout as $type => [$groupCol, $labelCol, $startCol]) {
            $gb = null;
            for ($r = 4; $r <= min(10000, $sheet->getHighestDataRow()); $r++) {
                $group = trim((string)$sheet->getCell([$groupCol, $r])->getValue());
                if (preg_match('/^(?:GB\s*)?([1-6])$/i', $group, $m)) $gb = (int)$m[1];
                $label = strtoupper(trim((string)$sheet->getCell([$labelCol, $r])->getValue()));
                if (!$gb || !in_array($label, ['BIAYA EXISTING', 'BIAYA NEW PRODUCT'], true)) continue;
                $code = ($label === 'BIAYA EXISTING' ? 'existing' : 'new') . $gb;
                if (isset($rows[$type][$code])) throw new InvalidArgumentException('Subtotal biaya berulang: ' . $type . ' ' . $code);
                $values = [];
                for ($i = 0; $i < 12; $i++) {
                    $cell = $sheet->getCell([$startCol + $i, $r]);
                    $v = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                    if ($v !== null && $v !== '' && $v !== '-' && !is_numeric($v)) throw new InvalidArgumentException('Nilai biaya tidak valid pada ' . $cell->getCoordinate());
                    // A SUM over empty product cells has a cached zero. Keep that
                    // subtotal blank; an explicitly entered numeric zero remains zero.
                    if ($cell->isFormula() && is_numeric($v) && (float)$v === 0.0) {
                        $address = $cell->getCoordinate();
                        if (!biayaHasNumericSource($sheet, $address)) $v = null;
                    }
                    $values[] = is_numeric($v) ? (float)$v : null;
                }
                $rows[$type][$code] = $values;
            }
        }
    }
    if (empty($rows['target']) || empty($rows['actual'])) throw new InvalidArgumentException('Gunakan file biaya dengan tahun sesuai periode, target (TARGET ROLLING untuk 2025) dan realisasi Januari–Desember.');
    return $rows;
}

function findBiayaRecord($conn, string $period): ?array
{
    try {
        // BIAYA is uploaded as a full-year workbook. Use the latest upload for
        // that year even when its selected upload month is after the report
        // month (for example, an October upload must also serve September).
        $first = substr($period, 0, 4) . '-01';
        $last = substr($period, 0, 4) . '-12';
        $q = $conn->prepare('SELECT payload FROM biaya_files WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');
        $q->bind_param('ss', $first, $last);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        return $row ? json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR) : null;
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1146) return null;
        throw $e;
    }
}

function populateBiayaSheet(Worksheet $sheet, string $period, $conn): void
{
    $year = (int)substr($period, 0, 4);
    $month = (int)substr($period, 5, 2);
    for ($r = $sheet->getHighestDataRow(); $r >= 1; $r--) {
        if (!preg_match('/^BIAYA TAHUN (20\d{2})$/', trim((string)$sheet->getCell('A'.$r)->getValue()), $m)) continue;
        $y = (int)$m[1];
        $data = findBiayaRecord($conn, $y . ($y === $year ? substr($period, 4) : '-12')) ?? [];
        // Clone the biaya block before inserting, so formulas and later sections shift normally.
        $targetMerges = [];
        foreach ($sheet->getMergeCells() as $merge) {
            $bounds = Coordinate::rangeBoundaries($merge);
            if ($bounds[0][1] >= $r && $bounds[1][1] < $r+29) $targetMerges[] = $merge;
        }
        $heights = [];
        for ($i=0;$i<29;$i++) $heights[$i] = $sheet->getRowDimension($r+$i)->getRowHeight();
        $snapshot = [];
        for ($i = 0; $i < 29; $i++) for ($c = 1; $c <= 16; $c++) {
            $cell = $sheet->getCell([$c, $r+$i]);
            $snapshot[$i][$c] = [$cell->getValue(), $cell->getXfIndex()];
        }
        $sheet->insertNewRowBefore($r, 29);
        foreach ($snapshot as $i => $cells) foreach ($cells as $c => [$v, $style]) {
            $cell = $sheet->getCell([$c, $r+$i]);
            $cell->setValue($c >= 3 && $i >= 2 ? null : $v);
            $cell->setXfIndex($style);
        }
        foreach ($targetMerges as $merge) $sheet->mergeCells($merge);
        foreach ($heights as $i=>$height) $sheet->getRowDimension($r+$i)->setRowHeight($height);
        $sheet->setCellValue('A'.$r, 'TARGET BIAYA '.$y);
        foreach (['target' => $r, 'actual' => $r+29] as $type => $start) {
            for ($i = 2; $i < 29; $i++) {
                $label = strtoupper(trim((string)$sheet->getCell('B'.($start+$i))->getValue()));
                $values = null;
                if (preg_match('/^(EXIST|NP|TOTAL) GB ([1-6])$/', $label, $match)) {
                    $a = $data[$type]['existing'.$match[2]] ?? array_fill(0,12,null);
                    $b = $data[$type]['new'.$match[2]] ?? array_fill(0,12,null);
                    $values = $match[1] === 'EXIST' ? $a : ($match[1] === 'NP' ? $b : array_map(static fn($x,$y) => $x === null && $y === null ? null : ($x ?? 0)+($y ?? 0), $a,$b));
                }
                for ($c = 3; $c <= 16; $c++) $sheet->getCell([$c,$start+$i])->setValue(null);
                if ($values === null) continue;
                for ($j=0;$j<12;$j++) $sheet->getCell([$j+4,$start+$i])->setValue($values[$j]);
                $sum = static fn($v) => count(array_filter($v, static fn($x)=>$x!==null)) ? array_sum($v) : null;
                $sheet->setCellValue('C'.($start+$i),$sum(array_slice($values,0,$month)));
                $sheet->setCellValue('P'.($start+$i),$sum($values));
            }
        }
    }
}

/** Clear values and formulas from the current and previous MCM sections. */
function clearMcmSheet(Worksheet $sheet, int $reportYear): void
{
    $highest = $sheet->getHighestDataRow();
    $findHeading = static function (string $heading) use ($sheet, $highest): ?int {
        for ($r = 1; $r <= $highest; $r++) {
            if (strtoupper(trim((string)$sheet->getCell('A'.$r)->getValue())) === $heading) return $r;
        }
        return null;
    };
    for ($year = $reportYear - 1; $year <= $reportYear; $year++) {
        $mcmStart = $findHeading('MCM TAHUN '.$year);
        if ($mcmStart === null) continue;
        for ($r = $mcmStart + 2; $r <= $highest; $r++) {
            $heading = strtoupper(trim((string)$sheet->getCell('A'.$r)->getValue()));
            if (preg_match('/^(?:REVENUE|BIAYA|MCM|TARGET SELL OUT|TARGET BIAYA) TAHUN 20\d{2}$/', $heading)) break;
            if (trim((string)$sheet->getCell('B'.$r)->getValue()) === '') continue;
            for ($column = 3; $column <= 16; $column++) $sheet->getCell([$column, $r])->setValue(null);
        }
    }
}
