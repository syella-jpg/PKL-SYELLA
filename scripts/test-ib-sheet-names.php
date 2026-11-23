<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/ib_template_history.php';
require __DIR__.'/../dashboard/revenue_ibfm_processor.php';
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
$book=buildReportInputTemplate('IB','2026-09');
foreach(['IB FARMA','IB FOOD','GM IB'] as $name)if(!$book->getSheetByName($name))throw new RuntimeException('Missing new sheet '.$name);
foreach(['IB FARMA','IB FOOD','GM IB'] as $name){$s=$book->getSheetByName($name);$s->setCellValue('E7',100);$s->setCellValue('F7',250);}
validateReportInputTemplate($book,'IB','2026-09');
$path=tempnam('/tmp','ib-names-synthetic-');
try{
 foreach([['IB FARMA','IB FOOD','GM IB'],['YFR - IBFM','IAP','HRU'],['YRU','IAP','HRU']] as $names){
  foreach(['IB FARMA','IB FOOD','GM IB'] as $i=>$name)getIbInputSheet($book,$name)->setTitle($names[$i]);
  validateReportInputTemplate($book,'IB','2026-09');(new Xlsx($book))->save($path);
  foreach(['IB FARMA','IB FOOD','GM IB'] as $i=>$name){
   $metric=$i===2?'MCM = REVENUE - HPP – EXPENSES':'SALES REVENUE (USD)';
   $result=processRevenueExistIbFmSpreadsheet($path,2026,$name,true,$metric);
   if($result['actual'][2]!==150.0)throw new RuntimeException('Old/new alias not read: '.$name);
  }
 }
}finally{unlink($path);$book->disconnectWorksheets();}
echo "PASS synthetic template: new sheet names; old HRU/IAP/YFR/YRU aliases; validator and monthly reader.\n";
