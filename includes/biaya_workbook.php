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

function biayaSheetYear(Worksheet $sheet): ?int
{
    $cell=$sheet->getCell('E3');
    $value=$cell->isFormula()?$cell->getCalculatedValue():$cell->getValue();
    if (preg_match('/^(20\d{2})$/',trim((string)$value),$match)) return (int)$match[1];
    return null;
}

function parseBiayaWorkbook(\PhpOffice\PhpSpreadsheet\Spreadsheet $book, int $year): array
{
    $rows = [];
    $foundYear=false; $availableYears=[];
    foreach ($book->getAllSheets() as $sheet) {
        $sheetYear=biayaSheetYear($sheet);
        if ($sheetYear!==null) $availableYears[]=$sheetYear;
        if ($sheetYear !== $year) continue;
        $foundYear=true;
        $rollingHeading = strtoupper(trim((string)$sheet->getCell('X1')->getValue()));
        $hasRolling = $year === 2025 && str_contains($rollingHeading, 'TARGET ROLLING');
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
    if (!$foundYear) {
        $detected=$availableYears ? implode(', ',array_unique($availableYears)) : 'tidak terbaca pada sel E3';
        throw new InvalidArgumentException('Periode upload memilih tahun '.$year.', tetapi tahun file BIAYA '.$detected.'. Periksa tahun di sel E3 dan periode upload.');
    }
    if (empty($rows['target']) || empty($rows['actual'])) {
        $target=$year===2025?'TARGET ROLLING':'TARGET';
        throw new InvalidArgumentException('Blok '.$target.' atau REALISASI Januari–Desember pada file BIAYA '.$year.' tidak ditemukan.');
    }
    return $rows;
}

/** Product rows mirror the uploaded workbook; totals are calculated by SQL. */
function parseBiayaProducts(\PhpOffice\PhpSpreadsheet\Spreadsheet $book, int $year): array
{
    // Validate year, monthly headers and rolling layout before accepting rows.
    parseBiayaWorkbook($book, $year);
    $rows = [];
    foreach ($book->getAllSheets() as $sheet) {
        if (biayaSheetYear($sheet) !== $year) continue;
        $rolling = $year === 2025 && str_contains(strtoupper((string)$sheet->getCell('X1')->getValue()), 'TARGET ROLLING');
        $layouts = $rolling ? ['target'=>[20,22,23,24], 'actual'=>[38,39,40,42]]
                            : ['target'=>[1,3,4,6], 'actual'=>[20,21,22,23]];
        for ($r=4; $r<=min(10000,$sheet->getHighestDataRow()); $r++) {
            $category = strtoupper(trim((string)$sheet->getCell('B'.$r)->getValue()));
            foreach ($layouts as $type=>[$groupCol,$numberCol,$nameCol,$monthCol]) {
                $gb = trim((string)$sheet->getCell([$groupCol,$r])->getValue());
                $numberCell = $sheet->getCell([$numberCol,$r]);
                $number = $numberCell->isFormula() ? $numberCell->getCalculatedValue() : $numberCell->getValue();
                if (!preg_match('/^[1-6]$/',$gb) || !is_numeric($number)) continue;
                if (!in_array($category,['EXIS','D'],true)) throw new InvalidArgumentException('Kategori produk harus EXIS atau D pada baris '.$r.'.');
                $values = [];
                for ($m=0;$m<12;$m++) {
                    $cell=$sheet->getCell([$monthCol+$m,$r]);
                    $v=$cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
                    // Spreadsheet templates use a dash as an empty amount.
                    if (is_string($v)) $v=trim(str_replace("\xc2\xa0", ' ', $v));
                    if ($v===null || $v==='' || in_array($v,['-','–','—'],true)) $v=null;
                    elseif (!is_numeric($v)) throw new InvalidArgumentException('Nilai biaya tidak valid pada '.$cell->getCoordinate().'.');
                    elseif ($cell->isFormula() && (float)$v===0.0 && !biayaHasNumericSource($sheet,$cell->getCoordinate())) $v=null;
                    $values[]= $v===null ? null : (float)$v;
                }
                $key=$type.':'.$gb.':'.(int)$number;
                if (isset($rows[$key])) throw new InvalidArgumentException('Nomor produk biaya berulang: '.$key.'.');
                $rows[$key]=['type'=>$type,'gb'=>(int)$gb,'category'=>$category,'number'=>(int)$number,
                    'name'=>trim((string)$sheet->getCell([$nameCol,$r])->getValue()),'values'=>$values];
            }
        }
    }
    if (!$rows) throw new InvalidArgumentException('Rincian produk biaya tidak ditemukan.');
    return array_values($rows);
}

function saveBiayaProducts($conn, string $period, array $rows): void
{
    // Keep the schema with the upload code; no external SQL file is required.
    $monthlyColumns=implode(',',array_map(
        static fn($month)=>'`'.$month.'` DECIMAL(20,6) NULL',
        ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
    ));
    $conn->query("CREATE TABLE IF NOT EXISTS biaya (
        report_year SMALLINT UNSIGNED NOT NULL,
        jenis ENUM('target','actual') NOT NULL,
        gb TINYINT UNSIGNED NOT NULL,
        kategori ENUM('EXIS','D') NOT NULL,
        nomor_produk INT UNSIGNED NOT NULL,
        nama_produk VARCHAR(255) NOT NULL,
        $monthlyColumns,
        PRIMARY KEY (report_year,jenis,gb,nomor_produk)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query('CREATE TABLE IF NOT EXISTS biaya_uploads (report_year SMALLINT UNSIGNED PRIMARY KEY, report_period CHAR(7) NOT NULL, uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $year=(int)substr($period,0,4);
    $conn->begin_transaction();
    try {
        // Lock the annual upload, so concurrent uploads replace a year atomically.
        $q=$conn->prepare('INSERT INTO biaya_uploads (report_year,report_period) VALUES (?,?) ON DUPLICATE KEY UPDATE report_period=VALUES(report_period), uploaded_at=CURRENT_TIMESTAMP');
        $q->bind_param('is',$year,$period); $q->execute(); $q->close();
        $q=$conn->prepare('DELETE FROM biaya WHERE report_year=?');
        $q->bind_param('i',$year); $q->execute(); $q->close();
        $months=['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
        $q=$conn->prepare('INSERT INTO biaya (report_year,jenis,gb,kategori,nomor_produk,nama_produk,'.implode(',',array_map(static fn($m)=>'`'.$m.'`',$months)).') VALUES ('.implode(',',array_fill(0,18,'?')).')');
        foreach ($rows as $row) {
            $args=[$year,$row['type'],$row['gb'],$row['category'],$row['number'],$row['name'],...$row['values']];
            $q->bind_param('isisis'.str_repeat('d',12),...$args); $q->execute();
        }
        $q->close(); $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}

/** The annual product data serves every download month; SQL preserves NULLs. */
function findBiayaRecord($conn, string $period): ?array
{
    if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$period)) throw new InvalidArgumentException('Periode biaya tidak valid.');
    $months=['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
    try {
        $sums=implode(',',array_map(static fn($m)=>'SUM(`'.$m.'`) AS `'.$m.'`',$months));
        $q=$conn->prepare('SELECT jenis,gb,kategori,'.$sums.' FROM biaya WHERE report_year=? GROUP BY jenis,gb,kategori');
        $year=(int)substr($period,0,4); $q->bind_param('i',$year); $q->execute();
        $result=$q->get_result(); $data=[];
        while ($row=$result->fetch_assoc()) {
            $code=($row['kategori']==='EXIS'?'existing':'new').$row['gb'];
            $data[$row['jenis']][$code]=array_map(static fn($m)=>$row[$m]===null?null:(float)$row[$m],$months);
        }
        $q->close(); return $data ?: null;
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode()===1146) return null;
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
            if (preg_match('/^(?:(?:REVENUE|BIAYA|MCM) TAHUN|TARGET SELL OUT|TARGET BIAYA) 20\d{2}$/', $heading)) break;
            for ($column = 3; $column <= 16; $column++) $sheet->getCell([$column, $r])->setValue(null);
        }
    }
}

/** MCM = revenue minus actual biaya, matched by year and product label. */
function populateMcmSheet(Worksheet $sheet, int $reportYear): void
{
    clearMcmSheet($sheet, $reportYear);
    $sections = [];
    $section = null;
    $normalize = static fn($v) => preg_replace('/\s+/u', ' ', strtoupper(trim((string)$v)));
    for ($r=1; $r<=$sheet->getHighestDataRow(); $r++) {
        $heading = $normalize($sheet->getCell('A'.$r)->getValue());
        if (preg_match('/^(?:(REVENUE|BIAYA|MCM) TAHUN|TARGET SELL OUT|TARGET BIAYA) (20\d{2})$/', $heading, $m)) {
            $section = !empty($m[1]) ? $m[1].' '.$m[2] : null;
            continue;
        }
        $label = $normalize($sheet->getCell('B'.$r)->getValue());
        if ($section && preg_match('/^(?:(?:EXIST|NP|TOTAL) GB [1-6]|(?:EXIST|NP|TOTAL) ETH(?:ICAL)?)$/', $label)) {
            $label = preg_replace('/ ETH$/', ' ETHICAL', $label);
            $sections[$section][$label] = $r;
        }
    }
    foreach ([$reportYear-1, $reportYear] as $year) foreach ($sections['MCM '.$year] ?? [] as $label=>$row) {
        $rev = $sections['REVENUE '.$year][$label] ?? null;
        $biaya = $sections['BIAYA '.$year][$label] ?? null;
        if (!$rev || !$biaya) continue;
        for ($c=3; $c<=16; $c++) {
            $col = Coordinate::stringFromColumnIndex($c);
            $a = $sheet->getCell($col.$rev);
            $av = $a->isFormula() ? $a->getOldCalculatedValue() : $a->getValue();
            $b = $sheet->getCell($col.$biaya);
            $bv = $b->isFormula() ? $b->getOldCalculatedValue() : $b->getValue();
            // Numeric zero is data. Missing inputs remain genuinely empty.
            if (!is_int($av) && !is_float($av)) continue;
            if (!is_int($bv) && !is_float($bv)) continue;
            $cell = $sheet->getCell($col.$row);
            $cell->setValue('='.$col.$rev.'-'.$col.$biaya);
            $cell->setCalculatedValue($av-$bv);
        }
    }
}

/** Source revenue is rupiah; the EDP report uses Rp (000), like biaya. */
function scaleEdpRevenueToThousands(Worksheet $sheet): void
{
    $revenue = false;
    for ($r=1; $r<=$sheet->getHighestDataRow(); $r++) {
        $heading = strtoupper(trim((string)$sheet->getCell('A'.$r)->getValue()));
        if (preg_match('/^(?:(?:REVENUE|BIAYA|MCM) TAHUN|TARGET SELL OUT|TARGET BIAYA) 20\d{2}$/', $heading)) {
            $revenue = str_starts_with($heading, 'REVENUE TAHUN ');
            continue;
        }
        if (!$revenue || !preg_match('/^(EXIST|NP|TOTAL)\s/', strtoupper(trim((string)$sheet->getCell('B'.$r)->getValue())))) continue;
        for ($c=3; $c<=16; $c++) {
            $cell=$sheet->getCell([$c,$r]);
            $value=$cell->isFormula()?$cell->getOldCalculatedValue():$cell->getValue();
            if (!is_int($value) && !is_float($value)) continue;
            $cell->setValue($value/1000);
        }
    }
}
