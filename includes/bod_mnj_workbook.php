<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;

function validateBodMnjPeriod(string $period): void
{
    if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $period) || (int)substr($period, 0, 4) < 2025) {
        throw new InvalidArgumentException('Periode BOD MNJ tidak valid.');
    }
}

function getBodMnjSheet(Spreadsheet $book): Worksheet
{
    return $book->getSheetByName('BOD MNJ') ?? $book->getSheet(0);
}

/** Validate the source heading, including the 2026 title below its summary. */
function validateBodMnjYear(Spreadsheet $book, int $year): void
{
    $sheet = getBodMnjSheet($book);
    $text = [];
    foreach ($sheet->getCellCollection()->getCoordinates() as $address) {
        [, $row] = Coordinate::coordinateFromString($address);
        if ($row <= 100) {
            $value = $sheet->getCell($address)->getValue();
            if (is_string($value)) $text[] = $value;
        }
    }
    $heading = implode(' ', $text);
    preg_match_all('/SALES\s+MNJ\s+TH\s*(20\d{2})\b/i', $heading, $matches);
    $years = array_unique(array_map('intval', $matches[1]));
    if (count($years) !== 1 || !in_array($year, $years, true)) {
        throw new InvalidArgumentException('Tahun SALES MNJ pada file berbeda dari periode yang dipilih. Unggah file asli untuk satu tahun.');
    }
}

/** Read the uploaded binary without rewriting its cells or formatting. */
function decodeBodMnjWorkbook(string $base64, int $year): Spreadsheet
{
    if (strlen($base64) > 70 * 1024 * 1024 || ($bytes = base64_decode($base64, true)) === false || $bytes === '') {
        throw new InvalidArgumentException('File BOD MNJ tidak valid atau terlalu besar.');
    }
    $temporary = tempnam(sys_get_temp_dir(), 'bod-mnj-');
    if ($temporary === false) throw new RuntimeException('File sementara BOD MNJ gagal dibuat.');
    try {
        if (file_put_contents($temporary, $bytes) === false) throw new RuntimeException('File BOD MNJ gagal dibaca.');
        try {
            $reader = IOFactory::createReaderForFile($temporary);
            if (!$reader instanceof Ods && !$reader instanceof Xlsx) throw new InvalidArgumentException('Gunakan file ODS atau XLSX.');
            $book = $reader->load($temporary);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $error) {
            throw new InvalidArgumentException('File BOD MNJ harus berupa ODS atau XLSX yang dapat dibaca.', 0, $error);
        }
        validateBodMnjYear($book, $year);
        return $book;
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
}

/** Latest upload up to the requested month, strictly within the selected year. */
function findBodMnjRecord($conn, string $period): ?array
{
    validateBodMnjPeriod($period);
    try {
        $query = $conn->prepare('SELECT report_period, payload FROM bod_mnj_files WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');
        if (!$query) {
            if ((int)$conn->errno === 1146) return null;
            throw new RuntimeException('Data BOD MNJ gagal dibaca.');
        }
        try {
            $first = substr($period, 0, 4) . '-01';
            $query->bind_param('ss', $first, $period);
            if (!$query->execute()) throw new RuntimeException('Data BOD MNJ gagal dibaca.');
            $record = $query->get_result()->fetch_assoc();
            return $record ? array_merge(json_decode($record['payload'], true, 512, JSON_THROW_ON_ERROR), ['period' => $record['report_period']]) : null;
        } finally { $query->close(); }
    } catch (mysqli_sql_exception $error) {
        // Fresh installations can use the supplied annual files before their first upload.
        if ((int)$error->getCode() === 1146) return null;
        throw $error;
    }
}

/** Extract the non-product target/actual blocks into the user's relational table. */
function parseBodMnjNonProduct(Spreadsheet $book, int $defaultYear): array
{
    $sheet=getBodMnjSheet($book); $rows=[]; $year=null; $section=false;
    for ($r=1;$r<=$sheet->getHighestDataRow();$r++) {
        $label=strtoupper(trim((string)$sheet->getCell('B'.$r)->getValue()));
        if (str_contains($label,'BIAYA NON PRODUCT')) { $section=true; $year=null; continue; }
        if (!$section) continue;
        if (preg_match('/^20\d{2}$/',$label)) { $year=(int)$label; continue; }
        if (!in_array($label,['TARGET','AKTUAL'],true)) continue;
        $values=[];
        for ($c=4;$c<=15;$c++) {
            $cell=$sheet->getCell([$c,$r]);
            $value=$cell->isFormula()?$cell->getCalculatedValue():$cell->getValue();
            if (is_string($value)) $value=trim($value);
            if ($value===null || $value==='' || in_array($value,['-','–','—'],true)) $value=null;
            elseif (!is_numeric($value)) throw new InvalidArgumentException('Nilai BIAYA NON PRODUCT tidak valid pada '.$cell->getCoordinate().'.');
            $values[]=$value===null?null:(float)$value;
        }
        $key=($year??$defaultYear).':'.$label;
        if(isset($rows[$key])) throw new InvalidArgumentException('Blok BIAYA NON PRODUCT berulang.');
        $rows[$key]=['year'=>$year??$defaultYear,'type'=>strtolower($label),'values'=>$values];
    }
    if (!$rows) throw new InvalidArgumentException('Bagian BIAYA NON PRODUCT target/aktual tidak ditemukan.');
    return array_values($rows);
}

function saveBodMnjRecord($conn, string $period, string $base64): void
{
    validateBodMnjPeriod($period);
    if (!$conn->query('CREATE TABLE IF NOT EXISTS bod_mnj_files (report_period CHAR(7) PRIMARY KEY, payload LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) {
        throw new RuntimeException('Tabel BOD MNJ gagal disiapkan.');
    }
    $book=decodeBodMnjWorkbook($base64,(int)substr($period,0,4));
    try { $nonProduct=parseBodMnjNonProduct($book,(int)substr($period,0,4)); }
    finally { $book->disconnectWorksheets(); }
    $conn->begin_transaction();
    try {
    $query = $conn->prepare('INSERT INTO bod_mnj_files (report_period, payload) VALUES (?, ?) ON DUPLICATE KEY UPDATE payload = VALUES(payload)');
    if (!$query) throw new RuntimeException('Data BOD MNJ gagal disimpan.');
    try {
        $payload = json_encode(['base64' => $base64], JSON_THROW_ON_ERROR);
        $query->bind_param('ss', $period, $payload);
        if (!$query->execute()) throw new RuntimeException('Data BOD MNJ gagal disimpan.');
    } finally { $query->close(); }
    $months=['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'];
    $columns=implode(',',array_map(static fn($m)=>'`'.$m.'`',$months));
    $updates=implode(',',array_map(static fn($m)=>'`'.$m.'`=VALUES(`'.$m.'`)',$months));
    $query=$conn->prepare('INSERT INTO bod_mnj (periode,tahun_data,jenis,'.$columns.') VALUES ('.implode(',',array_fill(0,15,'?')).') ON DUPLICATE KEY UPDATE '.$updates);
    try {
        foreach ($nonProduct as $row) {
            $args=[$period.'-01',$row['year'],$row['type'],...$row['values']];
            $query->bind_param('sis'.str_repeat('d',12),...$args);
            if (!$query->execute()) throw new RuntimeException('Rincian BOD MNJ gagal disimpan.');
        }
    } finally { $query->close(); }
    $conn->commit();
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
}

function readBodMnjWorkbook(int $year, string $period, $conn = null): ?Spreadsheet
{
    validateBodMnjPeriod($period);
    if ((int)substr($period, 0, 4) !== $year) throw new InvalidArgumentException('Tahun dan periode BOD MNJ tidak sesuai.');
    $record = $conn ? findBodMnjRecord($conn, $period) : null;
    if ($record) return decodeBodMnjWorkbook($record['base64'], $year);
    $file = __DIR__ . '/../dashboard/assets/bod-mnj-' . $year . '.ods';
    if (!is_file($file)) return null;
    $book = IOFactory::load($file);
    validateBodMnjYear($book, $year);
    return $book;
}

/** Provide the native formulas because browser ODS readers can lose absolute references. */
function getBodMnjFormulaMap(Spreadsheet $book): array
{
    $sheet = getBodMnjSheet($book);
    $formulas = [];
    foreach ($sheet->getCellCollection()->getCoordinates() as $address) {
        $cell = $sheet->getCell($address);
        if ($cell->isFormula()) $formulas[$address] = substr($cell->getValue(), 1);
    }
    return $formulas;
}

/** ODS files contain formatted empty trailing rows; they are not annual data. */
function getBodMnjLastDataRow(Worksheet $sheet): int
{
    $last = 0;
    foreach ($sheet->getCellCollection()->getCoordinates() as $address) {
        $value = $sheet->getCell($address)->getValue();
        if ($value !== null && $value !== '') {
            [, $row] = Coordinate::coordinateFromString($address);
            $last = max($last, $row);
        }
    }
    return $last;
}

/** Recompute YTD from January through the report month for an annual source sheet. */
function populateBodMnjYtd(Worksheet $sheet, string $period): void
{
    validateBodMnjPeriod($period);
    $month = (int)substr($period, 5, 2);
    $names = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
    $norm = static fn($value) => preg_replace('/\s+/u', ' ', strtoupper(trim((string)$value)));
    $key = static function ($label) {
        if (preg_match('/^GB\s*(\d)/', $label, $m)) return 'GB' . $m[1];
        return str_contains($label, 'FARMASI') ? 'FARMASI' : $label;
    };
    $value = static function ($address) use ($sheet) {
        $cell = $sheet->getCell($address);
        if (!$cell->isFormula()) return $cell->getValue();
        $cached = $cell->getOldCalculatedValue();
        return $cached !== null ? $cached : $sheet->getCell($address)->getCalculatedValue();
    };
    $put = static function ($address, $v, $formula = null) use ($sheet) {
        $cell = $sheet->getCell($address);
        if ($formula !== null) {
            $cell->setValueExplicit('=' . $formula, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $cell->setCalculatedValue($v);
        } else {
            $cell->setValueExplicit($v, is_numeric($v) && !is_string($v) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
    };
    $last = getBodMnjLastDataRow($sheet);
    $tables = [];
    for ($row = 1; $row <= $last; $row++) {
        if ($norm($value('D' . $row)) !== 'JAN' || !in_array($norm($value('O' . $row)), ['DES', 'DEC'], true)) continue;
        $rows = [];
        for ($r = $row + 1; $r <= $last; $r++) {
            if (!preg_match('/^(GB\s*\d|TOT|ETHICAL|EXPORT|GRAND|TARGET$|AKTUAL$|ACTUAL$|%$)/', $norm($value('B' . $r)))) break;
            $rows[] = $r;
        }
        $tables[] = ['header' => $row, 'rows' => $rows];
    }
    foreach ($tables as $table) foreach ($table['rows'] as $row) for ($column = 4; $column <= 15; $column++) {
        $address = Coordinate::stringFromColumnIndex($column) . $row;
        $cell = $sheet->getCell($address);
        if (!$cell->isFormula()) continue;
        $oldCached = $cell->getOldCalculatedValue();
        $formula = preg_replace_callback('/\$?C\$?\d+\b/', static function ($m) use ($value) {
            $original = $value(str_replace('$', '', $m[0]));
            if (!is_int($original) && !is_float($original)) throw new RuntimeException('Nilai sumber rumus BOD MNJ tidak tersedia: ' . $m[0]);
            return '(' . json_encode($original, JSON_PRESERVE_ZERO_FRACTION) . ')';
        }, $cell->getValue());
        // Reacquire after reading other cells: PhpSpreadsheet cell handles detach.
        $sheet->getCell($address)->setValueExplicit($formula, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
        $sheet->getCell($address)->setCalculatedValue($oldCached);
    }
    // Snapshot monthly amounts before replacing any cumulative or summary cells.
    $monthly = [];
    foreach ($tables as $table) foreach ($table['rows'] as $row) {
        if ($norm($value('B' . $row)) === '%') continue;
        $monthly[$row] = [];
        for ($column = 4; $column < 4 + $month; $column++) {
            $amount = $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row)->getCalculatedValue();
            if (is_int($amount) || is_float($amount)) $monthly[$row][] = $amount;
        }
    }
    foreach ($sheet->getCellCollection()->getCoordinates() as $address) {
        $v = $sheet->getCell($address)->getValue();
        if (is_string($v) && preg_match('/^S\/D\s+/i', trim($v))) $put($address, preg_replace('/S\/D\s+[A-Z]+/i', 'S/D ' . $names[$month - 1], $v));
    }
    $detail = [];
    foreach ($tables as $table) {
        $header = $table['header'];
        $put('C' . ($norm($value('B' . $header)) === 'RP.000- PPN' ? $header - 1 : $header), 'S/D ' . $names[$month - 1]);
        $byLabel = [];
        foreach ($table['rows'] as $row) {
            $label = $norm($value('B' . $row));
            if ($label === '%') {
                $actual = $value('C' . ($row - 1));
                $target = $value('C' . ($row - 2));
                $put('C' . $row, is_numeric($actual) && is_numeric($target) && (float)$target != 0 ? $actual / $target : '', 'IFERROR(C' . ($row - 1) . '/C' . ($row - 2) . ',"")');
            } else {
                $range = 'D' . $row . ':' . Coordinate::stringFromColumnIndex($month + 3) . $row;
                $amounts = $monthly[$row];
                $put('C' . $row, $amounts ? array_sum($amounts) : '', 'IF(COUNT(' . $range . ')=0,"",SUM(' . $range . '))');
            }
            $byLabel[$key($label)] = $row;
        }
        $detail[] = $byLabel;
    }
    if (($tables[0]['header'] ?? 0) > 5 && count($detail) >= 2) {
        for ($row = 1; $row < $tables[0]['header']; $row++) {
            $label = $key($norm($value('B' . $row)));
            $salesRow = $detail[0][$label] ?? null;
            $promoRow = $detail[1][$label] ?? null;
            if (!$salesRow || !$promoRow) continue;
            $put('C' . $row, $value('C' . $salesRow), 'C' . $salesRow);
            $put('D' . $row, $value('C' . $promoRow), 'C' . $promoRow);
            foreach (['E' => 'C', 'I' => 'G'] as $column => $denominator) {
                $num = $value('D' . $row); $den = $value($denominator . $row);
                $put($column . $row, is_numeric($num) && is_numeric($den) && (float)$den != 0 ? $num / $den : '', 'IFERROR(D' . $row . '/' . $denominator . $row . ',"")');
            }
        }
    }
    \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($sheet->getParent())->clearCalculationCache();
}

/** Current year on top; preceding year's latest upload below, with three empty rows. */
function buildBodMnjWorkbook(int $year, string $period, $conn = null): ?Spreadsheet
{
    $book = readBodMnjWorkbook($year, $period, $conn);
    if (!$book) return null;
    $sheet = getBodMnjSheet($book);
    $sheet->setTitle('BOD MNJ');
    populateBodMnjYtd($sheet, $period);
    scaleBodNonProduct2025($sheet);
    $previous = $year > 2025 ? readBodMnjWorkbook($year - 1, ($year - 1) . '-12', $conn) : null;
    if (!$previous) return $book;
    $source = getBodMnjSheet($previous);
    populateBodMnjYtd($source, ($year - 1) . substr($period, 4));
    scaleBodNonProduct2025($source);
    $source->setTitle('BOD MNJ prior source');
    // Transfer the actual worksheet so its parent and style indices stay valid.
    $book->addExternalSheet($source);
    $offset = getBodMnjLastDataRow($sheet) + 3;
    $lastPrior = getBodMnjLastDataRow($source);
    foreach ($source->getCellCollection()->getCoordinates() as $address) {
        [$column, $row] = Coordinate::coordinateFromString($address);
        if ($row > $lastPrior) continue;
        $original = $source->getCell($address);
        $target = $sheet->getCell($column . ($row + $offset));
        $value = $original->getValue();
        if ($original->isFormula()) {
            $value = ReferenceHelper::getInstance()->updateFormulaReferences($value, 'A1', 0, $offset, $source->getTitle(), true);
            $value = str_replace("'BOD MNJ prior source'!", "'BOD MNJ'!", $value);
        }
        $target->setValueExplicit($value, $original->getDataType());
        if ($original->isFormula()) $target->setCalculatedValue($original->getOldCalculatedValue());
        $target->setXfIndex($original->getXfIndex());
        if ($original->hasHyperlink()) $target->setHyperlink(clone $original->getHyperlink());
    }
    foreach ($source->getMergeCells() as $range) {
        $sheet->mergeCells(preg_replace_callback('/([A-Z]+)(\d+)/', static fn($m) => $m[1] . ((int)$m[2] + $offset), $range));
    }
    foreach ($source->getRowDimensions() as $row => $dimension) {
        if ($row > $lastPrior) continue;
        $target = $sheet->getRowDimension($row + $offset);
        $target->setRowHeight($dimension->getRowHeight());
        $target->setVisible($dimension->getVisible());
    }
    $sheet->getPageSetup()->setPrintArea('A1:' . $sheet->getHighestDataColumn() . ($offset + $lastPrior));
    $book->removeSheetByIndex($book->getIndex($source));
    return $book;
}

/** Populate only the requested actual cost ETHICAL row; other sections stay intact. */
function fillEdpEthicalCost2025(Spreadsheet $book, string $period, int $year = 2025): void
{
    $target=$book->getSheetByName('REVENUE EDP MNJ');
    $source=$book->getSheetByName('BOD MNJ');
    if (!$target || !$source) return;
    $norm=static fn($v)=>preg_replace('/\s+/u',' ',strtoupper(trim((string)$v)));
    $find=static function($sheet,$heading,$label) use($norm) {
        $inside=false;
        for($r=1;$r<=$sheet->getHighestDataRow();$r++) {
            $a=$norm($sheet->getCell('A'.$r)->getValue());
            $b=$norm($sheet->getCell('B'.$r)->getValue());
            if($a===$heading || $b===$heading){$inside=true;continue;}
            if(!$inside)continue;
            if(preg_match('/^(?:(?:REVENUE|BIAYA|MCM) TAHUN|TARGET BIAYA|TARGET SELL OUT|SALES MNJ TH|BIAYA PROMOSI MNJ TH) 20\d{2}$/',$a.'') || preg_match('/^(SALES MNJ TH|BIAYA PROMOSI MNJ TH) 20\d{2}$/',$b))break;
            if($b===$label)return $r;
        }
        return null;
    };
    $from=$find($source,'BIAYA PROMOSI MNJ TH '.$year,'ETHICAL');
    $to=$find($target,'BIAYA TAHUN '.$year,'TOTAL ETHICAL');
    if(!$from || !$to)return;
    $values=[];
    for($m=0;$m<12;$m++) {
        $address=Coordinate::stringFromColumnIndex($m+4).$from;
        $cell=$source->getCell($address);
        $value=$cell->isFormula()?$cell->getOldCalculatedValue():$cell->getValue();
        $value=is_int($value)||is_float($value)?$value:null;
        $values[]=$value;
        $dest=$target->getCell([$m+4,$to]);
        if($value===null)$dest->setValue(null);
        else {$dest->setValue("='BOD MNJ'!".$address);$dest->setCalculatedValue($value);}
    }
    foreach(['C'=>(int)substr($period,5,2),'P'=>12] as $column=>$count) {
        $selected=array_slice($values,0,$count);$cell=$target->getCell($column.$to);
        if(!count(array_filter($selected,static fn($v)=>$v!==null)))$cell->setValue(null);
        else {$cell->setValue('=SUM(D'.$to.':'.Coordinate::stringFromColumnIndex($count+3).$to.')');$cell->setCalculatedValue(array_sum($selected));}
    }
}

/** Refresh only TOTAL ETHICAL after its BOD cost has been loaded. */
function fillEdpMcmEthical(Spreadsheet $book): void
{
    $sheet=$book->getSheetByName('REVENUE EDP MNJ');
    if(!$sheet)return;
    $norm=static fn($v)=>preg_replace('/\s+/u',' ',strtoupper(trim((string)$v)));
    $sections=[];$section='';
    for($r=1;$r<=$sheet->getHighestDataRow();$r++) {
        $heading=$norm($sheet->getCell('A'.$r)->getValue());
        if(preg_match('/^(?:(?:REVENUE|BIAYA|MCM) TAHUN|TARGET BIAYA|TARGET SELL OUT) 20\d{2}$/',$heading))$section=$heading;
        if($norm($sheet->getCell('B'.$r)->getValue())==='TOTAL ETHICAL')$sections[$section]=$r;
    }
    $numeric=static fn($v)=>is_int($v)||is_float($v);
    foreach([2025,2026] as $year) {
        $revenue=$sections['REVENUE TAHUN '.$year]??null;
        $cost=$sections['BIAYA TAHUN '.$year]??null;
        $target=$sections['MCM TAHUN '.$year]??null;
        if(!$revenue||!$cost||!$target)continue;
        for($c=3;$c<=16;$c++) {
            $col=Coordinate::stringFromColumnIndex($c);
            $a=$sheet->getCell($col.$revenue);$b=$sheet->getCell($col.$cost);
            $av=$a->isFormula()?$a->getOldCalculatedValue():$a->getValue();
            $bv=$b->isFormula()?$b->getOldCalculatedValue():$b->getValue();
            $cell=$sheet->getCell($col.$target);
            if(!$numeric($av)&&!$numeric($bv)){$cell->setValue(null);continue;}
            $cell->setValue('='.$col.$revenue.'-'.$col.$cost);
            $cell->setCalculatedValue(($numeric($av)?$av:0)-($numeric($bv)?$bv:0));
        }
    }
}

function scaleBodNonProduct2025(Worksheet $sheet): void
{
    $norm=static fn($v)=>strtoupper(trim((string)$v));
    for($r=1;$r<=$sheet->getHighestDataRow();$r++) {
        if(!str_starts_with($norm($sheet->getCell('B'.$r)->getValue()),'BIAYA NON PRODUCT') || $norm($sheet->getCell('B'.($r+1))->getValue())!=='2025')continue;
        for($row=$r+2;$row<=$r+4;$row++) {
            if(!in_array($norm($sheet->getCell('B'.$row)->getValue()),['TARGET','AKTUAL','ACTUAL'],true))continue;
            for($c=3;$c<=16;$c++) {
                $cell=$sheet->getCell([$c,$row]);
                $value=$cell->isFormula()?$cell->getOldCalculatedValue():$cell->getValue();
                if(!is_int($value)&&!is_float($value))continue;
                if($c===16) {
                    if($cell->isFormula()) {
                        $cell->setValue('=SUM(D'.$row.':O'.$row.')/1000');
                        $cell->setCalculatedValue($value);
                    }
                } elseif($cell->isFormula() && $c===3)$cell->setCalculatedValue($value*1000);
                else $cell->setValue($value*1000);
            }
        }
    }
}
