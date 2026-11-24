<?php
require __DIR__.'/../includes/tm_bod_analysis_map.php';
function verifyUpb($condition,$message){if(!$condition)throw new RuntimeException($message);}
foreach([2026,2027] as $year){
    $template=['slides'=>[['id'=>'dummy-cover','title'=>'Cover','subtitle'=>'','enabled'=>true],['id'=>'upb-custom-'.$year,'title'=>'Financial Perspective','subtitle'=>'  UPB   KX ','enabled'=>true]],'routes'=>['Other|Tab'=>['dummy-cover'],'UPB Konimex (RP)|Korporat'=>['dummy-cover']]];
    foreach(['Korporat','Barang Jadi','Rusak Produksi Non barang Jadi'] as $tab){
        verifyUpb(tmBodAnalysisTargetIds($template,'UPB Konimex (RP)',$tab)===['upb-custom-'.$year],'UPB goes only to correct slide');
        verifyUpb(tmBodAnalysisDestination('UPB Konimex (RP)',$tab)==='UPB KX','Fallback destination');
    }
    $template['slides'][]=['id'=>'mnj-custom-'.$year,'title'=>'Financial Perspective','subtitle'=>' UPB MNJ ','enabled'=>true];
    foreach(['Total UPB MNJ','GB 1 S/D GB 6','Ethical','Non Konimex'] as $tab){
        verifyUpb(tmBodAnalysisTargetIds($template,'UPB MNJ (RP)',$tab)===['mnj-custom-'.$year],'MNJ targets its own annual slide only');
        verifyUpb(tmBodAnalysisDestination('UPB MNJ (RP)',$tab)==='UPB MNJ','MNJ destination');
    }
    $template['slides'][2]['enabled']=false;
    try{tmBodAnalysisTargetIds($template,'UPB MNJ (RP)','Ethical');throw new LogicException('Disabled MNJ accepted');}catch(InvalidArgumentException $e){}
    $template['slides'][2]['enabled']=true;
    $ambiguous=$template;$ambiguous['slides'][]=['id'=>'duplicate','subtitle'=>'UPB MNJ','enabled'=>true];
    try{tmBodAnalysisTargetIds($ambiguous,'UPB MNJ (RP)','Ethical');throw new LogicException('Ambiguous MNJ accepted');}catch(InvalidArgumentException $e){}
    verifyUpb(tmBodAnalysisTargetIds($template,'Other','Tab')===['dummy-cover'],'Other routes preserved');
    foreach(range(1,12) as $month){
        $period=sprintf('%04d-%02d',$year,$month);$quarter='Q'.(int)ceil($month/3);
        verifyUpb(tmBodQuarterForPeriod($period)===$quarter,'Correct quarter');
        foreach(['Q1','Q2','Q3','Q4'] as $view){[$first,$last]=tmBodQuarterRange((string)$year,$view);verifyUpb(($period>=$first&&$period<=$last)===($quarter===$view),'Only matching quarter contains note');}
    }
    $template['slides'][1]['enabled']=false;
    try{tmBodAnalysisTargetIds($template,'UPB Konimex (RP)','Barang Jadi');throw new LogicException('Disabled destination accepted');}catch(InvalidArgumentException $e){}
}
foreach(['2026-00','2026-13','invalid'] as $period){try{tmBodQuarterForPeriod($period);throw new LogicException('Bad period accepted');}catch(InvalidArgumentException $e){}}
$api=file_get_contents(__DIR__.'/../dashboard/tm_bod_analysis_api.php');verifyUpb(str_contains($api,'WHERE id=? AND report_period=?'),'Note must belong to selected period');verifyUpb(str_contains($api,'WHERE report_period BETWEEN ? AND ?'),'Incoming list restricted to selected quarter');
echo "PASS dummy UPB destination for all tabs, custom annual slide IDs, 24 months across 2026/2027, quarter isolation, preserved routes, disabled slide and invalid period guards. No PPT/workbook/live DB accessed.\n";
