<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/ib_template_history.php';
require __DIR__.'/../dashboard/revenue_ibfm_processor.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function checkIb($value,$message){if(!$value)throw new RuntimeException($message);}
$keys=['target_ib_fm','actual_ib_fm','target_new_ib_fm','actual_new_ib_fm','target_ib_fd','actual_ib_fd','target_new_ib_fd','actual_new_ib_fd','target_mcm','actual_mcm'];
$rows=[];
foreach(range(1,12) as $m){
 $row=['report_period'=>'2026-11','month_number'=>$m];
 foreach($keys as $i=>$key)$row[$key]=$m<=11?($i+1)*10:null;
 $rows[]=$row;
}
$book=buildReportInputTemplate('IB','2026-12');
checkIb(fillIbTemplateHistory($book,'2026-12',$rows)==='2026-11','December continues November');
$s=$book->getSheetByName('YFR - IBFM');
checkIb($s->getCell('E6')->getValue()===10.0,'January cumulative');
checkIb($s->getCell('F6')->getValue()===20.0,'February restored cumulatively');
checkIb($s->getCell('O6')->getValue()===110.0,'November restored cumulatively');
checkIb($s->getCell('P6')->getValue()===null,'December input stays blank');
checkIb($book->getSheetByName('IAP')->getCell('F6')->getValue()===100.0,'Food mapping');
checkIb($book->getSheetByName('HRU')->getCell('F6')->getValue()===180.0,'MCM mapping');
$path=tempnam(sys_get_temp_dir(),'ib-history-');
try{
 IOFactory::createWriter($book,'Xlsx')->save($path);
 $parsed=processRevenueExistIbFmSpreadsheet($path,2026,'YFR - IBFM',true);
 checkIb($parsed['target'][2]===10.0,'Upload restored cumulative produces original monthly amount');
 checkIb($parsed['cumulative']['target'][2]===20.0,'Original cumulative retained independently');
}finally{unlink($path);}
foreach(['2026-01','2027-01','2027-02'] as $p){
 $empty=buildReportInputTemplate('IB',$p);
 checkIb(fillIbTemplateHistory($empty,$p,$rows)===null,'January/other years/future periods excluded');
 checkIb($empty->getSheetByName('YFR - IBFM')->getCell('E6')->getValue()===null,'No future or prior-year input');
}
$oct=buildReportInputTemplate('IB','2026-10');
checkIb(fillIbTemplateHistory($oct,'2026-10',$rows)==='2026-11','Later upload supplies earlier months if no October upload exists');
checkIb($oct->getSheetByName('YFR - IBFM')->getCell('N6')->getValue()===100.0,'October cumulative restored');
checkIb($oct->getSheetByName('YFR - IBFM')->getCell('O6')->getValue()===null,'November excluded when viewing October');
checkIb($oct->getSheetByName('YFR - IBFM')->getCell('P6')->getValue()===null,'December excluded when viewing October');
// An actual earlier snapshot keeps priority over later revisions.
$older=$rows;foreach($older as &$r){$r['report_period']='2026-09';$r['target_ib_fm']=1;}unset($r);
$oct=buildReportInputTemplate('IB','2026-10');
checkIb(fillIbTemplateHistory($oct,'2026-10',array_merge($rows,$older))==='2026-09','Existing historical snapshot is preferred');
checkIb($oct->getSheetByName('YFR - IBFM')->getCell('E6')->getValue()===1.0,'Earlier snapshot retained');
// Exact stored cumulative input recovers a known value even with earlier missing cells.
$raw=[];foreach(range(1,12) as $m)$raw[]=['report_period'=>'2026-11','month_number'=>$m,'metric_code'=>'revenue_exist_cum','business_unit'=>'IB_FM','value_type'=>'TARGET','amount'=>$m===3?null:($m===4?0:$m*10)];
$book=buildReportInputTemplate('IB','2026-12');fillIbTemplateHistory($book,'2026-12',$rows,$raw);
checkIb($book->getSheetByName('YFR - IBFM')->getCell('G6')->getValue()===null,'Original blank retained');
checkIb($book->getSheetByName('YFR - IBFM')->getCell('H6')->getValue()===0.0,'Original zero retained after gap');
checkIb($book->getSheetByName('YFR - IBFM')->getCell('I6')->getValue()===50.0,'Known cumulative after gap retained');
echo "PASS IB same-year carry, all Farma/Food/MCM target/actual mappings, cumulative reconstruction, upload roundtrip, exact gaps/zeros, January reset and period isolation\n";
