<?php
/** Shared Ethical product input, with independent TARGET and REAL UNIT/HNA. */
function ethicalSourceRows(): array {return ['unit'=>['existing'=>8,'streamline'=>9,'np_previous'=>10,'np_current'=>11],'hna'=>['existing'=>22,'streamline'=>23,'np_previous'=>24,'np_current'=>25]];}
function isEthicalSourceTemplate(\PhpOffice\PhpSpreadsheet\Spreadsheet $book): bool {return in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_ETHICAL_SOURCE_V1','KONIMEX_ETHICAL_SOURCE_V2','KONIMEX_ETHICAL_SOURCE_V3','KONIMEX_ETHICAL_SOURCE_V4','KONIMEX_ETHICAL_SOURCE_V5','KONIMEX_ETHICAL_SOURCE_V6'],true);}
function buildEthicalSubtotalTemplate(string $period): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$match))throw new InvalidArgumentException('Periode ETHICAL tidak valid.');
    $year=(int)$match[1];$month=(int)$match[2];$months=unitSellOutInputMonths();$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('PRODUK NAS');$sheet->setShowGridlines(false);
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->getColor()->setARGB('FF263449');
    $sheet->setCellValue('A1','ETHICAL — TARGET UNIT & HNA — TAHUN '.$year);$sheet->mergeCells('A1:O1');$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(19);$sheet->getRowDimension(1)->setRowHeight(36);
    $sheet->setCellValue('A2','PERIODE LAPORAN: '.$months[$month-1].' '.$year);$sheet->mergeCells('A2:O2');
    $sheet->setCellValue('A3','Salin empat baris subtotal TARGET dari PRODUK NAS ke sel kuning. Nilai per bulan, bukan kumulatif.');$sheet->mergeCells('A3:O3');
    $sheet->setCellValue('A4','Upload sekali di bagian ETHICAL. UNIT mengisi target Sell Out; HNA mengisi sheet ETHICAL.');$sheet->mergeCells('A4:O4');
    $sheet->getColumnDimension('A')->setWidth(32);foreach(range('B','O') as $c)$sheet->getColumnDimension($c)->setWidth(17);
    $labels=['existing'=>'TOTAL EXISTING','streamline'=>'TOTAL STREAMLINE','np_previous'=>'TOTAL NP '.($year-1),'np_current'=>'TOTAL NP '.$year];
    foreach(ethicalSourceRows() as $metric=>$rows){
        $title=$metric==='unit'?6:20;$header=$title+1;$first=reset($rows);$last=end($rows);$summary=$last+2;
        $sheet->setCellValue('A'.$title,$metric==='unit'?'UNIT — untuk TARGET SELL OUT di sheet UNIT':'HNA — untuk target pada sheet ETHICAL (Rupiah penuh)');$sheet->mergeCells('A'.$title.':O'.$title);
        $sheet->getStyle('A'.$title.':O'.$title)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF345C50');$sheet->getStyle('A'.$title)->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FFFFFFFF');$sheet->getRowDimension($title)->setRowHeight(30);
        foreach(['SUBTOTAL PRODUK NAS',...$months,'YTD '.$months[$month-1],'TOTAL FY'] as $c=>$v)$sheet->setCellValue([$c+1,$header],$v);
        foreach($rows as $kind=>$row)$sheet->setCellValue('A'.$row,$labels[$kind]);
        $sheet->setCellValue('A'.$summary,'EXISTING');$sheet->setCellValue('A'.($summary+1),'NP');$sheet->setCellValue('A'.($summary+2),'TOTAL ETHICAL');
        foreach(range('B','M') as $c){$sheet->setCellValue($c.$summary,'='.$c.$rows['existing'].'+'.$c.$rows['streamline']);$sheet->setCellValue($c.($summary+1),'='.$c.$rows['np_previous'].'+'.$c.$rows['np_current']);$sheet->setCellValue($c.($summary+2),'='.$c.$summary.'+'.$c.($summary+1));}
        foreach([...array_values($rows),$summary,$summary+1,$summary+2] as $row){$end=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+1);$sheet->setCellValue('N'.$row,'=SUM(B'.$row.':'.$end.$row.')');$sheet->setCellValue('O'.$row,'=SUM(B'.$row.':M'.$row.')');$sheet->getRowDimension($row)->setRowHeight(28);}
        $sheet->getStyle('A'.$header.':O'.$header)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$sheet->getStyle('A'.$header.':O'.$header)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$sheet->getRowDimension($header)->setRowHeight(30);
        $sheet->getStyle('B'.$first.':M'.$last)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
        foreach(['N'.$first.':O'.$last,'A'.$summary.':O'.($summary+2)] as $range)$sheet->getStyle($range)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
        $sheet->getStyle('A'.$summary.':O'.($summary+2))->getFont()->setBold(true);
        $sheet->getStyle('B'.$first.':O'.($summary+2))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
        $sheet->getStyle('A'.$header.':O'.($summary+2))->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE4EE');
        $rule=(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('decimal')->setOperator('greaterThanOrEqual')->setFormula1('0')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setError('Isi angka nol atau positif.');$sheet->setDataValidation('B'.$first.':M'.$last,$rule);
    }
    $sheet->freezePane('B8');$sheet->setSelectedCell('B8');$sheet->getSheetView()->setZoomScale(85);$sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:O29');
    $meta=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([['KONIMEX_ETHICAL_SOURCE_V1'],['ETHICAL'],[$year],[$period]]);$meta->setSheetState('hidden');return $book;
}
function ethicalSourceNumber($value,string $address,bool $allowNegative=false): ?float {
    if($value===null||$value===''||in_array(trim((string)$value),['-','–','—'],true))return null;
    if(!is_numeric($value)||!is_finite((float)$value)||(!$allowNegative&&(float)$value<0)||abs((float)$value)>=1e17)throw new InvalidArgumentException('Nilai Ethical tidak valid pada '.$address.'. '.($allowNegative?'Isi angka yang valid, termasuk negatif untuk koreksi.':'Isi angka nol atau positif.'));
    return (float)$value;
}
function ethicalSourceSummary(array $inputs): array {
    $summary=[];
    foreach(array_values(array_intersect(['unit','hna','actual_unit','actual_hna'],array_keys($inputs))) as $metric)foreach(['existing'=>['existing','streamline'],'new'=>['np_previous','np_current']] as $kind=>$parts)for($m=0;$m<12;$m++){
        $total=null;foreach($parts as $part){$value=$inputs[$metric][$part][$m]??null;if($value!==null)$total=($total??0)+(float)$value;}$summary[$metric][$kind][$m]=$total;
    }
    return $summary;
}
function parseEthicalSourceTemplate(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): array {
    if(in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_ETHICAL_SOURCE_V2','KONIMEX_ETHICAL_SOURCE_V3','KONIMEX_ETHICAL_SOURCE_V4','KONIMEX_ETHICAL_SOURCE_V5','KONIMEX_ETHICAL_SOURCE_V6'],true))return parseEthicalProductSource($book,$period);
    $year=(int)substr($period,0,4);if(!isEthicalSourceTemplate($book)||(int)$book->getSheetByName('_TEMPLATE')->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template ETHICAL berbeda dari periode upload.');
    $sheet=$book->getSheetByName('PRODUK NAS');if(!$sheet)throw new InvalidArgumentException('Sheet PRODUK NAS tidak ditemukan.');$inputs=[];$hasValue=false;
    $labels=['existing'=>'TOTAL EXISTING','streamline'=>'TOTAL STREAMLINE','np_previous'=>'TOTAL NP '.($year-1),'np_current'=>'TOTAL NP '.$year];
    foreach(ethicalSourceRows() as $metric=>$rows){$header=$metric==='unit'?7:21;
        foreach(unitSellOutInputMonths() as $m=>$label)if($sheet->getCell([$m+2,$header])->getValue()!==$label)throw new InvalidArgumentException('Kolom bulan template ETHICAL berubah.');
        foreach($rows as $kind=>$r){if($sheet->getCell('A'.$r)->getValue()!==$labels[$kind])throw new InvalidArgumentException('Label subtotal ETHICAL berubah pada baris '.$r.'.');
            for($m=0;$m<12;$m++){$cell=$sheet->getCell([$m+2,$r]);try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus ETHICAL tidak dapat dihitung pada '.$cell->getCoordinate().'.');}$inputs[$metric][$kind][$m]=ethicalSourceNumber($v,$cell->getCoordinate(),true);$hasValue=$hasValue||$inputs[$metric][$kind][$m]!==null;}
        }
    }
    if(!$hasValue)throw new InvalidArgumentException('Template ETHICAL masih kosong. Isi minimal satu nilai TARGET UNIT atau HNA.');
    return ['year'=>$year,'inputs'=>$inputs,'summary'=>ethicalSourceSummary($inputs)];
}
/** Legacy source: use PRODUK NAS alone, never add its derived PID/PE sheets a second time. */
function parseEthicalNasSource(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): ?array {
    $sheet=$book->getSheetByName('PRODUK NAS');if(!$sheet)return null;
    $aliases=['JAN'=>1,'JANUARI'=>1,'FEB'=>2,'FEBRUARI'=>2,'MAR'=>3,'MARET'=>3,'APR'=>4,'APRIL'=>4,'MEI'=>5,'MAY'=>5,'JUN'=>6,'JUNI'=>6,'JUL'=>7,'JULI'=>7,'AGS'=>8,'AGT'=>8,'AUG'=>8,'AGUSTUS'=>8,'SEP'=>9,'SEPTEMBER'=>9,'OKT'=>10,'OCT'=>10,'OKTOBER'=>10,'NOV'=>11,'NOVEMBER'=>11,'DES'=>12,'DEC'=>12,'DESEMBER'=>12];$columns=[];
    $norm=static fn($v)=>strtoupper(trim(preg_replace('/\s+/u',' ',(string)$v)));
    $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    for($c=1;$c<=$last;$c++)if(isset($aliases[$norm($sheet->getCell([$c,1])->getValue())]))$columns[$aliases[$norm($sheet->getCell([$c,1])->getValue())]]=$c;
    if(count($columns)!==12)return null;$year=(int)substr($period,0,4);
    $wanted=['TOTAL EXISTING'=>'existing','TOTAL STREAMLINE'=>'streamline','TOTAL NP '.($year-1)=>'np_previous','TOTAL NP '.$year=>'np_current','TOTAL NP '.substr((string)($year-1),2)=>'np_previous','TOTAL NP '.substr((string)$year,2)=>'np_current'];$found=[];
    for($r=4;$r<=$sheet->getHighestDataRow();$r++){$label=$norm($sheet->getCell('B'.$r)->getValue());if(!isset($wanted[$label]))continue;$key=$wanted[$label];if(isset($found[$key]))throw new InvalidArgumentException('Subtotal '.$label.' berulang pada PRODUK NAS.');$found[$key]=$r;}
    if(!isset($found['existing']))throw new InvalidArgumentException('TOTAL EXISTING tidak ditemukan pada PRODUK NAS.');
    $inputs=[];$hasValue=false;
    foreach($columns as $month=>$base){
        if($norm($sheet->getCell([$base,2])->getValue())!=='TARGET'||$norm($sheet->getCell([$base,3])->getValue())!=='UNIT'||$norm($sheet->getCell([$base+1,3])->getValue())!=='HNA')throw new InvalidArgumentException('Kolom TARGET UNIT/HNA PRODUK NAS tidak sesuai pada bulan '.$month.'.');
        foreach(['unit'=>0,'hna'=>1] as $metric=>$offset)foreach($wanted as $label=>$kind){
            if(!isset($found[$kind])){$inputs[$metric][$kind][$month-1]=0.0;continue;}
            $cell=$sheet->getCell([$base+$offset,$found[$kind]]);
            try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus subtotal PRODUK NAS tidak dapat dihitung pada '.$cell->getCoordinate().'.');}
            $v=ethicalSourceNumber($v,$cell->getCoordinate(),true);$inputs[$metric][$kind][$month-1]=$v;$hasValue=$hasValue||$v!==null;
        }
    }
    if(!$hasValue)throw new InvalidArgumentException('Subtotal TARGET UNIT/HNA PRODUK NAS masih kosong.');
    return ['year'=>$year,'inputs'=>$inputs,'summary'=>ethicalSourceSummary($inputs)];
}
function ensureEthicalSourceStore(mysqli $conn): void {
    $conn->query('CREATE TABLE IF NOT EXISTS ethical_source_inputs (report_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,payload MEDIUMBLOB NOT NULL,source_period CHAR(7) NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $conn->query('CREATE TABLE IF NOT EXISTS ethical_source_monthly (report_year SMALLINT UNSIGNED NOT NULL,kind VARCHAR(12) NOT NULL,month TINYINT UNSIGNED NOT NULL,unit_target DECIMAL(24,6) NULL,hna_target DECIMAL(24,6) NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(report_year,kind,month)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function loadEthicalSourceMonthly(mysqli $conn,int $year,bool $lock=false): array {
    try{$q=$conn->prepare('SELECT report_year,kind,month,unit_target,hna_target FROM ethical_source_monthly WHERE report_year=? ORDER BY kind,month'.($lock?' FOR UPDATE':''));$q->bind_param('i',$year);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();return $rows;}catch(mysqli_sql_exception $e){if($e->getCode()===1146)return [];throw $e;}
}
function ethicalSourceUnitInputs(array $rows): array {
    $parts=['existing'=>array_fill(0,12,null),'new'=>array_fill(0,12,null)];foreach($rows as $row){if(isset($parts[$row['kind']])&&(int)$row['month']>=1&&(int)$row['month']<=12)$parts[$row['kind']][(int)$row['month']-1]=$row['unit_target']===null?null:(float)$row['unit_target'];}return $parts;
}
function saveEthicalSource(mysqli $conn,string $period,array $data): void {
    $year=$data['year'];$payload=json_encode($data['inputs'],JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION);
    $q=$conn->prepare('INSERT INTO ethical_source_inputs (report_year,payload,source_period) VALUES (?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),source_period=VALUES(source_period)');$q->bind_param('iss',$year,$payload,$period);$q->execute();$q->close();
    $q=$conn->prepare('DELETE FROM ethical_source_monthly WHERE report_year=?');$q->bind_param('i',$year);$q->execute();$q->close();
    $q=$conn->prepare('INSERT INTO ethical_source_monthly (report_year,kind,month,unit_target,hna_target) VALUES (?,?,?,?,?)');
    foreach(['existing','new'] as $kind)for($month=1;$month<=12;$month++){$unit=$data['summary']['unit'][$kind][$month-1];$hna=$data['summary']['hna'][$kind][$month-1];$unit=$unit===null?null:number_format($unit,6,'.','');$hna=$hna===null?null:number_format($hna,6,'.','');$q->bind_param('isiss',$year,$kind,$month,$unit,$hna);$q->execute();}$q->close();
    $q=$conn->prepare("DELETE FROM `target unit sell out` WHERE report_year=? AND product='ethical'");$q->bind_param('i',$year);$q->execute();$q->close();
    $q=$conn->prepare("INSERT INTO `target unit sell out` (report_year,product,kind,month,units) VALUES (?,'ethical',?,?,?)");
    foreach(['existing','new'] as $kind)for($month=1;$month<=12;$month++){$units=$data['summary']['unit'][$kind][$month-1];$units=$units===null?null:number_format($units,6,'.','');$q->bind_param('isis',$year,$kind,$month,$units);$q->execute();}$q->close();
    saveUnitSellOutInputs($conn,$year,$period,['ethical'],['ethical'=>$data['summary']['unit']]);
}
/** Master data wins over stale/edited copies in the combined target template. */
function applyEthicalSourceTargets(mysqli $conn,int $year,array $data): array {
    if(!in_array('ethical',array_column($data['records'],'product'),true))return $data;
    $rows=loadEthicalSourceMonthly($conn,$year,true);if(!$rows)return $data;$parts=ethicalSourceUnitInputs($rows);
    $data['records']=array_values(array_filter($data['records'],fn($r)=>$r['product']!=='ethical'));
    foreach($parts as $kind=>$values)foreach($values as $m=>$v)$data['records'][]=['report_year'=>$year,'product'=>'ethical','kind'=>$kind,'month'=>$m+1,'units'=>$v===null?null:number_format($v,6,'.','')];
    if(isset($data['inputs']))$data['inputs']['ethical']=$parts;$data['ethical_from_source']=true;return $data;
}

// The compact source supplies TARGET HNA/UNIT; preserve unrelated legacy HJP/Actual metrics.
function mergeEthicalSourceData(array $legacy,array $sourceRows): array {
    $byYear=[];foreach($legacy as $record)$byYear[(int)$record['year']]=$record;
    foreach($sourceRows as $row){
        $year=(int)$row['report_year'];$month=(int)$row['month']-1;$kind=$row['kind'];if($month<0||$month>11||!in_array($kind,['existing','new'],true))continue;
        if(!isset($byYear[$year]))$byYear[$year]=['year'=>$year,'target'=>array_fill(0,12,0.0),'actual'=>array_fill(0,12,0.0),'newTarget'=>array_fill(0,12,0.0),'newActual'=>array_fill(0,12,0.0),'hnaExisting'=>array_fill(0,12,null),'hnaNew'=>array_fill(0,12,null)];
        $hnaKey=$kind==='existing'?'hnaExisting':'hnaNew';$unitKey=$kind==='existing'?'unitExisting':'unitNew';
        $byYear[$year][$hnaKey][$month]=$row['hna_target']===null?null:(float)$row['hna_target'];
        $byYear[$year][$unitKey]??=array_fill(0,12,null);$byYear[$year][$unitKey][$month]=$row['unit_target']===null?null:(float)$row['unit_target'];
    }
    foreach($byYear as &$record)$record['hnaTotal']=array_map(static fn($a,$b)=>($a??0)+($b??0),$record['hnaExisting'],$record['hnaNew']);unset($record);
    return array_values($byYear);
}

require_once __DIR__.'/ethical_product_source_template.php';

/** REAL uses its own fields; existing HJP metrics remain independent. */
function mergeEthicalSourceActuals(array $records,array $savedInputs): array {
    foreach($savedInputs as $saved){
        $inputs=json_decode($saved['payload'],true,512,JSON_THROW_ON_ERROR);
        if(!isset($inputs['actual_unit'],$inputs['actual_hna']))continue;
        $summary=ethicalSourceSummary($inputs);
        foreach($records as &$record){if((int)$record['year']!==(int)$saved['report_year'])continue;
            foreach(['existing'=>'Existing','new'=>'New'] as $kind=>$suffix){$record['unitActual'.$suffix]=$summary['actual_unit'][$kind];$record['hnaActual'.$suffix]=$summary['actual_hna'][$kind];}
        }unset($record);
    }
    return $records;
}
