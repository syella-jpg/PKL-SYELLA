<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/unit_sell_out_targets.php';
function checkEthicalTarget($test,$message){if(!$test)throw new RuntimeException($message);}
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->setTitle('GMEB');$s->setCellValue('A5','TAHUN : 2027');$s->setCellValue('A6','1. PERSPEKTIF FINANCIAL');$s->setCellValue('M7','TARGET 2027');$s->setCellValue('O7','REALISASI 2027');
$months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'];foreach($months as $m=>$label)$s->setCellValue([16+$m,8],$label);
$s->setCellValue('B9','Revenue Growth');$s->setCellValue('C9','Revenue Selling Out (Rp)');$s->setCellValue('D12','Existing Product (Rp)');$s->setCellValue('O12','Target');$s->setCellValue('P12',99999);
$s->setCellValue('C18','Revenue Selling Out (Unit)');$s->setCellValue('O18','Target');$s->setCellValue('P18',88888);
$s->setCellValue('D21','Existing Product (Unit)');$s->setCellValue('O21','Target');$s->setCellValue('O22','Aktual');
$s->setCellValue('D24','New Product (Unit)');$s->setCellValue('O24','Target');$s->setCellValue('O25','Aktual');
$s->setCellValue('C27','Ratio Biaya Marketing');$s->setCellValue('D28','Existing Product (Unit)');$s->setCellValue('O28','Target');$s->setCellValue('P28',77777);
$s->setCellValue('A31','2. PERSPEKTIF CUSTOMER');$s->setCellValue('B34','Revenue Growth');$s->setCellValue('C34','Revenue Selling Out (Unit)');$s->setCellValue('D35','Existing Product (Unit)');$s->setCellValue('O35','Target');$s->setCellValue('P35',66666);
for($m=1;$m<=12;$m++){$s->setCellValue([15+$m,21],$m*10);$s->setCellValue([15+$m,24],$m*2);$s->setCellValue([15+$m,22],9999);$s->setCellValue([15+$m,25],8888);}
$s->setCellValue('M21',55555);$s->setCellValue('M24',44444);
$rows=parseUnitSellOutTargets($b,2027);checkEthicalTarget(count($rows)===24,'Wrong ETHICAL count');foreach($rows as $row){checkEthicalTarget($row['product']==='ethical','Wrong group');checkEthicalTarget((float)$row['units']===$row['month']*($row['kind']==='existing'?10.0:2.0),'Wrong target row/month or cumulative conversion');}
$s->setCellValue('Q21','=P21+10');$s->setCellValue('R21','=PM!R21');$s->getCell('R21')->setCalculatedValue(30);$s->setCellValue('P24',0);$s->setCellValue('Q24',null);
$rows=parseUnitSellOutTargets($b,2027);$values=[];foreach($rows as $row)$values[$row['kind'].'-'.$row['month']]=$row['units'];checkEthicalTarget($values['existing-2']==='20.000000','Local formula failed');checkEthicalTarget($values['existing-3']==='30.000000','Saved cross-sheet formula result failed');checkEthicalTarget($values['new-1']==='0.000000','Zero lost');checkEthicalTarget($values['new-2']===null,'Blank changed');
try{parseUnitSellOutTargets($b,2026);throw new RuntimeException('Wrong year accepted');}catch(InvalidArgumentException $expected){}
$s->setCellValue('O24','Aktual');try{parseUnitSellOutTargets($b,2027);throw new RuntimeException('Actual accepted as Target');}catch(InvalidArgumentException $expected){}$s->setCellValue('O24','Target');
$other=$b->createSheet();$other->setTitle('PM');$other->setCellValue('P21',999999);
$tmp=tempnam('/tmp','ethical-unit-target-');try{\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($b,'Ods')->save($tmp);$loaded=readUnitSellOutTargetBook($tmp);checkEthicalTarget($loaded->getSheetCount()===1,'Non-GMEB sheet imported');checkEthicalTarget(count(parseUnitSellOutTargets($loaded,2027))===24,'Filtered GMEB ODS failed');$loaded->disconnectWorksheets();}finally{unlink($tmp);}$b->disconnectWorksheets();
echo "PASS GMEB financial Revenue Growth/Selling Out Unit, Existing/New TARGET only, month/year, Rupiah/Actual/other sections ignored, saved cross-sheet/local formulas and blank/zero\n";
