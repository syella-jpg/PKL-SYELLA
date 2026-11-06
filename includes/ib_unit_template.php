<?php
/** Shared UNIT KPI inputs for IB/HRU and TARGET UNIT SELL OUT/IB. */
function ibUnitTemplateRows(bool $hru): array {
    $start=$hru?12:4;return ['title'=>$start,'header'=>$start+2,'A'=>$start+3,'actual_A'=>$start+4,'B'=>$start+6,'actual_B'=>$start+7,'calc'=>$start+10,'total'=>$start+12,'monthly'=>$start+12];
}
function appendIbUnitTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $s,string $period,bool $hru): void {
    $r=ibUnitTemplateRows($hru);$year=(int)substr($period,0,4);$periodMonth=(int)substr($period,5,2);$months=['JAN','S/D FEB','S/D MAR','S/D APR','S/D MEI','S/D JUN','S/D JUL','S/D AGS','S/D SEP','S/D OKT','S/D NOV','S/D DES'];
    $s->setCellValue('A'.$r['title'],'UNIT');$s->mergeCells('A'.$r['title'].':Q'.$r['title']);$s->getStyle('A'.$r['title'].':Q'.$r['title'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FF345C50');$s->getStyle('A'.$r['title'])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $s->setCellValue('F'.($r['header']-1),$year);$s->mergeCells('F'.($r['header']-1).':Q'.($r['header']-1));
    foreach(['NAMA OBJECTIVE','NAMA KPI','SUB KPI','PENILAIAN','JENIS',...$months] as $c=>$v)$s->setCellValue([$c+1,$r['header']],$v);
    foreach(['A'=>'SALES REVENUE (UNIT)','B'=>'SALES REVENUE NEW BUSINESS PRODUCT (UNIT)'] as $kind=>$label){$row=$r[$kind];foreach(['A'=>'REVENUE GROWTH','B'=>'VALUE SELLING IN','C'=>$label,'D'=>'TAHUNAN KUMULATIF'] as $col=>$v){$s->setCellValue($col.$row,$v);$s->mergeCells($col.$row.':'.$col.($row+1));}$s->setCellValue('E'.$row,'TARGET');$s->setCellValue('E'.($row+1),'REALISASI');$s->getStyle('F'.$row.':Q'.($row+1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');$rule=(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('decimal')->setOperator('greaterThanOrEqual')->setFormula1('0')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setError('Isi angka unit nol atau positif.');$s->setDataValidation('F'.$row.':Q'.($row+1),$rule);}
    $s->setCellValue('A'.$r['calc'],'KALKULASI IB — UNIT');$s->mergeCells('A'.$r['calc'].':Q'.$r['calc']);
    foreach(['PARAMETER','','','','',...$months] as $c=>$v)if($v!=='')$s->setCellValue([$c+1,$r['calc']+1],$v);$s->mergeCells('A'.($r['calc']+1).':E'.($r['calc']+1));
    foreach([0=>'IB PER BULAN — TARGET',1=>'IB PER BULAN — REALISASI'] as $i=>$label){$row=$r['total']+$i;$s->setCellValue('A'.$row,$label);$s->mergeCells('A'.$row.':E'.$row);for($m=0;$m<12;$m++){if($i===1&&$m>=$periodMonth)continue;$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($m+6);$prior=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($m+5);$a=$r[$i===0?'A':'actual_A'];$b=$r[$i===0?'B':'actual_B'];$formula=$m===0?'='.$col.$a.'+'.$col.$b:'=('.$col.$a.'+'.$col.$b.')-('.$prior.$a.'+'.$prior.$b.')';$s->setCellValue($col.$row,$formula);}}
    foreach([$r['header'],$r['calc'],$r['calc']+1] as $row){$s->getStyle('A'.$row.':Q'.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$s->getStyle('A'.$row.':Q'.$row)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');}
    $s->getStyle('A'.$r['total'].':Q'.($r['total']+1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');$s->getStyle('F'.$r['A'].':Q'.($r['total']+1))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$s->getStyle('A'.$r['header'].':Q'.($r['total']+1))->getAlignment()->setWrapText(true);$s->getStyle('A'.$r['header'].':Q'.($r['total']+1))->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE4EE');
    foreach(['A'=>30,'B'=>26,'C'=>48,'D'=>20,'E'=>16] as $col=>$width)$s->getColumnDimension($col)->setWidth($width);$s->getColumnDimension('Q')->setWidth(17);$s->getColumnDimension('E')->setWidth(17);
    for($row=$r['title'];$row<=$r['total']+1;$row++)$s->getRowDimension($row)->setRowHeight($row===$r['header']?36:30);
    $s->getPageSetup()->setPrintArea('A1:Q'.($r['total']+1))->setFitToHeight(0);
}
function readIbUnitTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $s,bool $hru): array {
    $r=ibUnitTemplateRows($hru);$parts=[];
    foreach(['A','B','actual_A','actual_B'] as $key){$row=$r[$key];$expected=str_ends_with($key,'B')?'SALES REVENUE NEW BUSINESS PRODUCT (UNIT)':'SALES REVENUE (UNIT)';$labelRow=str_starts_with($key,'actual_')?$row-1:$row;if($s->getCell('C'.$labelRow)->getValue()!==$expected||$s->getCell('E'.$row)->getValue()!==(str_starts_with($key,'actual_')?'REALISASI':'TARGET'))throw new InvalidArgumentException('Label tabel UNIT IB berubah.');
        foreach(['JAN','S/D FEB','S/D MAR','S/D APR','S/D MEI','S/D JUN','S/D JUL','S/D AGS','S/D SEP','S/D OKT','S/D NOV','S/D DES'] as $m=>$month){if($s->getCell([$m+6,$r['header']])->getValue()!==$month)throw new InvalidArgumentException('Kolom bulan UNIT IB berubah.');$cell=$s->getCell([$m+6,$row]);try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus UNIT IB tidak valid pada '.$cell->getCoordinate().'.');}if($v===null||$v===''||in_array(trim((string)$v),['-','–','—'],true))$v=null;elseif(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||abs((float)$v)>=1e17)throw new InvalidArgumentException('Isi angka UNIT IB nol atau positif pada '.$cell->getCoordinate().'.');else $v=(float)$v;$parts[$key][$m]=$v;}
    }return $parts;
}
function fillIbUnitTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $s,bool $hru,array $parts): void {
    $r=ibUnitTemplateRows($hru);foreach(['A','B','actual_A','actual_B'] as $key)foreach(($parts[$key]??array_fill(0,12,null)) as $m=>$v)if($v!==null)$s->setCellValue([$m+6,$r[$key]],(float)$v);
}
function ibUnitMonthlyRecords(int $year,array $parts): array {
    $records=[];$prior=0.0;for($m=0;$m<12;$m++){$total=$parts['A'][$m]===null||$parts['B'][$m]===null?null:$parts['A'][$m]+$parts['B'][$m];$value=$total===null||$prior===null?null:$total-$prior;$records[]=['report_year'=>$year,'product'=>'ib','kind'=>'total','month'=>$m+1,'units'=>$value===null?null:number_format($value,6,'.','')];$prior=$total;}return $records;
}
function loadIbUnitHistory(mysqli $conn,int $year,bool $lock=false): ?array {
    try{$q=$conn->prepare("SELECT payload FROM unit_sell_out_target_inputs WHERE report_year=? AND product='ib'".($lock?" FOR UPDATE":""));$q->bind_param('i',$year);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();return $row?json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR):null;}catch(mysqli_sql_exception $e){if($e->getCode()===1146)return null;throw $e;}
}
function mergeIbUnitParts(array $incoming,array $prior=[]): array {
    $parts=[];foreach(['A','B','actual_A','actual_B'] as $key)for($m=0;$m<12;$m++)$parts[$key][$m]=$incoming[$key][$m]??$prior[$key][$m]??null;return $parts;
}
/** Caller owns transaction; blank uploads do not erase previously supplied cumulative cells. */
function saveIbUnitInputs(mysqli $conn,int $year,string $period,array $incoming): void {
    $parts=mergeIbUnitParts($incoming,loadIbUnitHistory($conn,$year,true)??[]);
    saveUnitSellOutInputs($conn,$year,$period,['ib'],['ib'=>$parts]);
    $q=$conn->prepare("DELETE FROM `target unit sell out` WHERE report_year=? AND product='ib'");$q->bind_param('i',$year);$q->execute();$q->close();$q=$conn->prepare("INSERT INTO `target unit sell out` (report_year,product,kind,month,units) VALUES (?,'ib','total',?,?)");foreach(ibUnitMonthlyRecords($year,$parts) as $row){$month=$row['month'];$units=$row['units'];$q->bind_param('iis',$year,$month,$units);$q->execute();}$q->close();
}
