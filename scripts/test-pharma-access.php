<?php
require __DIR__.'/../includes/report_access.php';
function verifyPharma($ok,$message){if(!$ok)throw new RuntimeException($message);}
verifyPharma(!reportIsPharma(),'Default existing account behaviour');verifyPharma(reportInputGbs()===range(1,6),'Default all groups');
$GLOBALS['reportAccessUser']='pharma';verifyPharma(reportInputGbs()===[1,4],'Pharma only groups');
foreach(['Pharma','PHARMA','FARMASI'] as $tab)verifyPharma(reportPharmaTab('Revenue Konimex Selling Out (RP)',$tab),'Allowed Pharma tab');
foreach(['Corporate','HCE','Food','Ethical','International Business','MTM'] as $tab)verifyPharma(!reportPharmaTab('Revenue Konimex Selling Out (RP)',$tab),'Other tabs denied');
verifyPharma(!reportPharmaTab('TM BOD','Pharma'),'Unknown report denied');
$rows=[['gb'=>'1','name'=>'dummy-one'],['gb'=>'GB 2','name'=>'dummy-secret'],['gb'=>'4','name'=>'dummy-four'],['gb'=>'ET','name'=>'dummy-ethical']];
verifyPharma(count(reportPharmaRows($rows,'gb'))===2,'Read filter');
try{reportPharmaRows($rows,'gb',true);throw new LogicException('Mixed upload accepted');}catch(InvalidArgumentException $e){}
foreach(['unit_api','revenue_edp_mnj_api','revenue_so_api'] as $api){$data=reportPharmaFilter($api,['success'=>true,'data'=>$rows,'total'=>4]);verifyPharma($data['total']===2&&!str_contains(json_encode($data),'dummy-secret'),'Only permitted data returned');}
$targets=reportPharmaFilter('revenue_so_targets_api',['data'=>['rows'=>[['code'=>'category1A'],['code'=>'category2B'],['code'=>'nonTarget4'],['code'=>'new6']]]]);verifyPharma(count($targets['data']['rows'])===2,'Target records scoped');
$biaya=reportPharmaFilter('biaya_api',['data'=>['target'=>['existing1'=>[1],'new4'=>[2],'new2'=>[999]],'actual'=>['existing6'=>[999],'new1'=>[3]]]]);verifyPharma(!str_contains(json_encode($biaya),'999'),'Cost data scoped');
$notes=reportPharmaFilter('analysis_api',['data'=>['Revenue Konimex Selling Out (RP)|Pharma|2026-10'=>[['text'=>'allowed']],'Revenue Konimex Selling Out (RP)|Food|2026-10'=>[['text'=>'secret']]]]);verifyPharma(count((array)$notes['data'])===1&&!str_contains(json_encode($notes),'secret'),'Notes scoped');
echo "PASS dummy Pharma policy: pages/tabs/groups, disallowed mixed uploads, target/cost/revenue/analysis filtering. No DB or user files accessed.\n";
