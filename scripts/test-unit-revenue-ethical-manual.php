<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/unit_revenue_template_history.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function checkManual($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $period=$year.'-11';$book=buildUnitRevenueInputTemplate($period,2);$unit=$book->getSheetByName('UNIT');$rev=$book->getSheetByName('REVENUE');$dest=unitRevenueEthicalManualRows($book);
 checkManual(count($dest)===3,'Manual Existing/New/Total on revenue sheet');
 foreach(range(1,$unit->getHighestDataRow()) as $r)checkManual($unit->getCell('A'.$r)->getValue()!=='ETHICAL — REVENUE MANUAL','UNIT has no manual section');
 foreach([6=>['A',7],7=>['D',3]] as $r=>[$category,$qty]){$unit->setCellValue('A'.$r,'GB ET');$unit->setCellValue('D'.$r,'Synthetic '.$category);$unit->setCellValue('E'.$r,$category);$unit->setCellValue('F'.$r,$qty);$rev->setCellValue('F'.$r,8888);}
 $rev->setCellValue('F'.$dest['existing'],100);$rev->setCellValue('F'.$dest['new'],20);$rev->setCellValue('F'.$dest['total'],999);
 $rev->setCellValue('G'.$dest['total'],0);
 foreach(['Xlsx','Ods'] as $format){
  $path=tempnam(sys_get_temp_dir(),'ethical-manual-');
  try{
   IOFactory::createWriter($book,$format)->save($path);$loaded=IOFactory::load($path);$records=parseUnitRevenueInputTemplate($loaded,$period);
   $rows=normalizeUnitRevenueInputTemplate($records)->getSheet(0)->toArray(null,true,false);$sums=[];
   foreach($rows as $r)if(in_array($r[3]??null,['EXIST ETH','NP ETH','TOTAL ETH'],true))$sums[$r[3]]=$r;
   checkManual($sums['EXIST ETH'][18]===100.0&&$sums['NP ETH'][18]===20.0&&$sums['TOTAL ETH'][18]===999.0,'Manual revenue overrides product revenue and independent total retained');
   checkManual($sums['EXIST ETH'][5]===7.0&&$sums['NP ETH'][5]===3.0&&$sums['TOTAL ETH'][5]===10.0,'Unit quantities still sum original products');
   $manual=array_values(array_filter($records,fn($r)=>($r['__kind']??null)==='ethical_revenue'));
   checkManual($manual[2]['rev'][1]===0.0&&$manual[2]['rev'][2]===null,'Manual zero and unknown retained');
   $next=buildUnitRevenueTemplateWithHistory($year.'-12',['source_period'=>$period,'records'=>$records]);$nextRows=unitRevenueEthicalManualRows($next);
   checkManual($next->getSheetByName('REVENUE')->getCell('F'.$nextRows['total'])->getValue()===999.0,'Following month retains manual total');
   checkManual(count(parseUnitRevenueInputTemplate($next,$year.'-12'))===5,'Manual data survives next template upload');
   $jan=buildUnitRevenueTemplateWithHistory(($year+1).'-01',['source_period'=>$period,'records'=>$records]);$janRows=unitRevenueEthicalManualRows($jan);
   checkManual($jan->getSheetByName('REVENUE')->getCell('F'.$janRows['total'])->getValue()===null,'January manual inputs reset');
  }finally{unlink($path);}
 }
}
// Manual Ethical revenue can be uploaded before any Ethical unit details exist.
$book=buildUnitRevenueInputTemplate('2026-11',1);$dest=unitRevenueEthicalManualRows($book);$book->getSheetByName('REVENUE')->setCellValue('F'.$dest['total'],50);
$normalized=normalizeUnitRevenueInputTemplate(parseUnitRevenueInputTemplate($book,'2026-11'))->getSheet(0)->toArray(null,true,false);
checkManual(count($normalized)===7,'Only three Ethical summaries, no spurious products');
echo "PASS manual Ethical revenue EXIST/NP/TOTAL, independent totals, unit sums unchanged, XLSX/ODS upload, monthly carry, blank/zero and January reset\n";
