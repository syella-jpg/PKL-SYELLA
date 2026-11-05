<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/unit_revenue_input_template.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function verifyUr($ok,$message){if(!$ok)throw new RuntimeException($message);}
function rejectUr(callable $f,$message){try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $period=$year.'-11';$book=buildReportInputTemplate('UNIT & Revenue',$period);
 verifyUr($book->getSheetCount()===3,'Two compact sheets and hidden metadata');$unit=$book->getSheetByName('UNIT');$revenue=$book->getSheetByName('REVENUE');
 foreach(range(6,215) as $r){
  foreach(range(1,17) as $c)verifyUr($unit->getCell([$c,$r])->getValue()===null,'All unit inputs empty');
  foreach(range(6,17) as $c)verifyUr($revenue->getCell([$c,$r])->getValue()===null,'All rupiah inputs empty');
 }
 rejectUr(fn()=>parseUnitRevenueInputTemplate($book,$period),'Blank formula totals must not replace data');
 $fixtures=[['GB 1','00123','0009','Synthetic Existing','A',10,100],['GB 1','00124','0010','Synthetic New','D',3,50],['GB ET','00001','','Synthetic Ethical','ABC',7,70]];
 foreach($fixtures as $i=>$v){$r=6+$i;foreach(array_slice($v,0,5) as $c=>$id)$unit->getCell([$c+1,$r])->setValueExplicit($id,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$unit->setCellValue('F'.$r,$v[5]);$revenue->setCellValue('F'.$r,$v[6]);}
 $unit->setCellValue('G6',0);$revenue->setCellValue('G6',0);
 foreach(['Xlsx','Ods'] as $format){
  $path=tempnam(sys_get_temp_dir(),'unit-revenue-template-');
  try{
   IOFactory::createWriter($book,$format)->save($path);$loaded=IOFactory::load($path);$records=parseUnitRevenueInputTemplate($loaded,$period);
   verifyUr(count($records)===6,'Only detail rows read');verifyUr($records[0]['product_code']==='00123','Leading zeroes retained');verifyUr($records[0]['principal_product_code']==='0009','Principal leading zeroes retained');
   verifyUr($records[0]['qty'][1]===0.0&&$records[0]['qty'][2]===null,'Zero and missing are distinct');
   verifyUr($records[2]['gb']==='ET'&&$records[2]['kategori']==='ABC','Ethical/legacy Existing classification');
   $legacy=normalizeUnitRevenueInputTemplate($records);$rows=$legacy->getSheet(0)->rangeToArray('A5:AE13',null,true,false,false);
   $find=static fn($label)=>array_values(array_filter($rows,static fn($r)=>$r[3]===$label));
   verifyUr(count($find('TOTAL GB 1'))===1,'No duplicate GB subtotal');
   verifyUr($find('TOTAL GB 1')[0][5]===13.0&&$find('TOTAL GB 1')[0][18]===150.0,'Unit/rupiah subtotal exact');
   verifyUr($find('TOTAL EXISTING GB 1')[0][18]===100.0&&$find('TOTAL NEW PRODUCT GB 1')[0][18]===50.0,'Existing and New stay separate');
   verifyUr($find('TOTAL ETH')[0][4]==='TOTAL ETHICAL','Ethical supports UNIT and revenue readers in one row');
   verifyUr($rows[0][0]==='1','Detail GB numeric string avoids total fallback');
   rejectUr(fn()=>parseUnitRevenueInputTemplate($loaded,($year+1).'-11'),'Wrong year rejected');
   $loaded->disconnectWorksheets();$legacy->disconnectWorksheets();
  }finally{unlink($path);}
 }
 $unit->setCellValue('B6','TOTAL GB 1');rejectUr(fn()=>parseUnitRevenueInputTemplate($book,$period),'Subtotal labels in product code rejected');$unit->setCellValue('B6','00123');
 $unit->setCellValue('G6','invalid');rejectUr(fn()=>parseUnitRevenueInputTemplate($book,$period),'Invalid numeric text rejected');$unit->setCellValue('G6',0);
 foreach(range(1,5) as $c)$unit->getCell([$c,9])->setValueExplicit((string)$unit->getCell([$c,6])->getValue(),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
 rejectUr(fn()=>parseUnitRevenueInputTemplate($book,$period),'Duplicate identities rejected before DB join');
 $book->disconnectWorksheets();
}
$book=buildUnitRevenueInputTemplate('2026-11',2);$u=$book->getSheetByName('UNIT');$v=$book->getSheetByName('REVENUE');
foreach([6=>['GB ET','Synthetic existing','A',7],7=>['GB ET','Synthetic E','E',5]] as $r=>[$gb,$name,$cat,$qty]){$u->setCellValue('A'.$r,$gb);$u->setCellValue('D'.$r,$name);$u->setCellValue('E'.$r,$cat);$u->setCellValue('F'.$r,$qty);$v->setCellValue('F'.$r,$qty*10);}
$rows=normalizeUnitRevenueInputTemplate(parseUnitRevenueInputTemplate($book,'2026-11'))->getSheet(0)->toArray(null,true,false);
$found=[];foreach($rows as $r)if(in_array($r[3]??null,['EXIST ETH','NP ETH','TOTAL ETH'],true))$found[$r[3]]=$r;
verifyUr($found['EXIST ETH'][5]===12.0&&$found['NP ETH'][5]===0.0&&$found['TOTAL ETH'][5]===12.0,'Category E contributes to Existing and total Ethical');
$u->setCellValue('E6','KAT E');$u->setCellValue('E7','NP');
$aliasRows=normalizeUnitRevenueInputTemplate(parseUnitRevenueInputTemplate($book,'2026-11'))->getSheet(0)->toArray(null,true,false);
$aliasFound=[];foreach($aliasRows as $r)if(in_array($r[3]??null,['EXIST ETH','NP ETH','TOTAL ETH'],true))$aliasFound[$r[3]]=$r;
verifyUr($aliasFound['EXIST ETH'][5]===7.0&&$aliasFound['NP ETH'][5]===5.0&&$aliasFound['TOTAL ETH'][5]===12.0,'Ethical KAT E maps to EXIST and NP maps to New without double counting');
$u->setCellValue('E7','E');
$u->setCellValue('A7','GB 1');rejectUr(fn()=>parseUnitRevenueInputTemplate($book,'2026-11'),'Category E is Ethical only');
echo "PASS UNIT & Revenue empty templates, 2025/2026/2027, XLSX/ODS upload roundtrip, identities, zero/blank, exact subtotals, Ethical, duplicate/year/invalid input protection\n";
