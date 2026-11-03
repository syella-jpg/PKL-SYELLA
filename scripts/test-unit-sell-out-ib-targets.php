<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/unit_sell_out_targets.php';
function checkIbUnit($test,$message){if(!$test)throw new RuntimeException($message);}
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->setTitle('HRU');
$s->setCellValue('A1','FINANCIAL');$s->setCellValue('M2',2027);
$months=['JAN','S/D FEB','S/D MAR','S/D APR','S/D MEI','S/D JUN','S/D JUL','S/D AGS','S/D SEP','S/D OKT','S/D NOV','S/D DES'];
foreach($months as $m=>$label)$s->setCellValue([14+$m,3],$label);
$s->setCellValue('B4','VALUE SELLING IN');$s->setCellValue('C4','SALES REVENUE (RP)');$s->setCellValue('M4','TARGET');$s->setCellValue('N4',99999);
$s->setCellValue('A8','FINANCIAL');$s->setCellValue('M9',2027);foreach($months as $m=>$label)$s->setCellValue([14+$m,10],$label);$s->setCellValue('Z10','JAN s/d AGT');
$s->setCellValue('A11','REVENUE GROWTH');$s->setCellValue('B11','VALUE SELLING IN');$s->setCellValue('C11','SALES REVENUE (UNIT)');$s->setCellValue('M11','TARGET');$s->setCellValue('M12','REALISASI');
$s->setCellValue('B14','VALUE SELLING IN');$s->setCellValue('C14','SALES REVENUE NEW BUSINESS PRODUCT (UNIT)');$s->setCellValue('M14','TARGET');
for($m=1;$m<=12;$m++){$s->setCellValue([13+$m,11],$m*($m+1)*5);$s->setCellValue([13+$m,12],9999);$s->setCellValue([13+$m,14],$m*$m);}$s->setCellValue('Z11',7777);
$other=$b->createSheet();$other->setTitle('YFR');$other->setCellValue('N11',6666);
$rows=parseUnitSellOutTargets($b,2027);checkIbUnit(count($rows)===12,'Wrong IB record count');
foreach($rows as $r){checkIbUnit($r['product']==='ib'&&$r['kind']==='total','Wrong group');checkIbUnit((float)$r['units']===$r['month']*10.0+(2*$r['month']-1),'Cumulative monthly difference or wrong KPI row');}
$s->setCellValue('O11','=N11+20');$rows=parseUnitSellOutTargets($b,2027);checkIbUnit($rows[1]['units']==='23.000000','Formula not calculated');
try{parseUnitSellOutTargets($b,2026);throw new RuntimeException('Wrong year accepted');}catch(InvalidArgumentException $expected){}
$s->setCellValue('M11','REALISASI');try{parseUnitSellOutTargets($b,2027);throw new RuntimeException('Missing target accepted');}catch(InvalidArgumentException $expected){}$s->setCellValue('M11','TARGET');
$s->setCellValue('N11',0);$s->setCellValue('O11',0);$s->setCellValue('N14',0);$s->setCellValue('O14',0);$s->setCellValue('S11',null);$rows=parseUnitSellOutTargets($b,2027);checkIbUnit($rows[0]['units']==='0.000000'&&$rows[1]['units']==='0.000000','Zero lost');checkIbUnit($rows[5]['units']===null&&$rows[6]['units']===null,'Missing cumulative month creates guessed monthly amount');
$s->setCellValue('T14',null);$rows=parseUnitSellOutTargets($b,2027);checkIbUnit($rows[6]['units']===null&&$rows[7]['units']===null,'Blank B must not be silently omitted');
$s->setCellValue('M14','REALISASI');try{parseUnitSellOutTargets($b,2027);throw new RuntimeException('B Actual accepted as Target');}catch(InvalidArgumentException $expected){}$s->setCellValue('M14','TARGET');
$tmp=tempnam('/tmp','ib-unit-target-');try{\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($b,'Ods')->save($tmp);$loaded=readUnitSellOutTargetBook($tmp);checkIbUnit($loaded->getSheetCount()===1,'Other KPI sheets loaded');checkIbUnit(count(parseUnitSellOutTargets($loaded,2027))===12,'Filtered HRU ODS failed');$loaded->disconnectWorksheets();}finally{unlink($tmp);}$b->disconnectWorksheets();
echo "PASS HRU FINANCIAL exact KPI/sub KPI TARGET only, A+B kumulatif January and consecutive differences through December, Rupiah/Actual/other sheets excluded, formula/blank/zero/year and filtered ODS\n";
