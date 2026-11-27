<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/ib_template_history.php';
require __DIR__.'/../includes/raw_template_protection.php';
function ibEditableCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$rows=[];
foreach([['2026-10',10,100],['2026-11',10,999],['2026-10',11,1234],['2026-10',12,4567]] as [$p,$m,$v])$rows[]=['report_period'=>$p,'month_number'=>$m,'metric_code'=>'revenue_exist_cum','business_unit'=>'IB_FM','value_type'=>'ACTUAL','amount'=>$v];
$values=ibEditableCumulativeValues($rows,'2026-12');
ibEditableCheck($values['revenue_exist_cum']['IB_FM']['ACTUAL'][10]===100,'October correction wins over later stale snapshot');
ibEditableCheck(!isset($values['revenue_exist_cum']['IB_FM']['ACTUAL'][11]),'No November actual from October upload');
$rows[0]['amount']=null;$values=ibEditableCumulativeValues($rows,'2026-12');
ibEditableCheck(array_key_exists(10,$values['revenue_exist_cum']['IB_FM']['ACTUAL'])&&$values['revenue_exist_cum']['IB_FM']['ACTUAL'][10]===null,'Deletion stays blank, no resurrection');
$records=[];foreach(range(1,12) as $m)$records[]=['report_period'=>'2026-10','month_number'=>$m,'actual_ib_fm'=>10,'target_ib_fm'=>20,'actual_mcm'=>30,'target_mcm'=>40];
$b=buildReportInputTemplate('IB','2026-11');fillIbTemplateHistory($b,'2026-11',$records,$rows);
$f=getIbInputSheet($b,'IB FARMA');ibEditableCheck($f->getCell('N7')->getValue()===null,'October deleted value blank');ibEditableCheck($f->getCell('O7')->getValue()===null&&$f->getCell('P7')->getValue()===null,'November December stay blank');
protectRawTemplate($b,'2026-11','IB');$gm=getIbInputSheet($b,'GM IB');$r=ibUnitTemplateRows(true);
ibEditableCheck($gm->getProtection()->getSheet(),'GM IB protected');
foreach([['N7','protected'],['O7','unprotected'],['O6','protected'],['O'.$r['actual_A'],'protected'],['P'.$r['actual_A'],'unprotected'],['P'.$r['A'],'protected']] as [$cell,$lock])ibEditableCheck($gm->getStyle($cell)->getProtection()->getLocked()===$lock,'GM IB lock '.$cell);
$path=tempnam('/tmp','dummy-ib-lock-');try{$w=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($b);$w->setPreCalculateFormulas(false);$w->save($path);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);ibEditableCheck(getIbInputSheet($loaded,'GM IB')->getProtection()->getSheet(),'GM lock survives export');$loaded->disconnectWorksheets();}finally{unlink($path);$b->disconnectWorksheets();}
echo "PASS dummy IB: corrected/deleted month ownership, future actual blanks, GM USD and UNIT locks, XLSX export. No user files or database.\n";
