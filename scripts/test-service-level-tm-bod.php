<?php
require __DIR__.'/../includes/tm_bod_analysis_map.php';
function checkService($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([2026,2027] as $year){
    $template=['slides'=>[
        ['id'=>'dummy-wrong','subtitle'=>'UPB MNJ','enabled'=>true],
        ['id'=>'dummy-service-'.$year,'subtitle'=>' Service   Level Kx to MNJ ','enabled'=>true]
    ],'routes'=>['Service Level|SL Internal (KX to MNJ) – UNIT'=>['dummy-wrong']]];
    foreach(['SL Eksternal (MNJ to Outlet) – UNIT','SL Internal (KX to MNJ) – UNIT'] as $tab){
        checkService(tmBodAnalysisDestination('Service Level',$tab)==='Service Level Kx to MNJ','Correct destination');
        checkService(tmBodAnalysisTargetIds($template,'Service Level',$tab)===['dummy-service-'.$year],'Both tabs target exact annual slide');
        for($month=1;$month<=12;$month++){
            $period=sprintf('%d-%02d',$year,$month);$quarter='Q'.(int)ceil($month/3);
            checkService(tmBodQuarterForPeriod($period)===$quarter,'Quarter follows note period');
            foreach(['Q1','Q2','Q3','Q4'] as $view){
                [$first,$last]=tmBodQuarterRange((string)$year,$view);
                checkService(($period>=$first&&$period<=$last)===($quarter===$view),'No quarter leakage');
            }
        }
    }
    $missing=$template;$missing['slides'][1]['enabled']=false;
    try{tmBodAnalysisTargetIds($missing,'Service Level','Any');throw new LogicException('Disabled slide accepted');}catch(InvalidArgumentException $e){}
    $duplicate=$template;$duplicate['slides'][]=['id'=>'duplicate','title'=>'SERVICE LEVEL KX TO MNJ','enabled'=>true];
    try{tmBodAnalysisTargetIds($duplicate,'Service Level','Any');throw new LogicException('Duplicate accepted');}catch(InvalidArgumentException $e){}
}
echo "PASS dummy Service Level destinations for both tabs, annual IDs, quarter isolation, disabled/duplicate guards. No live data accessed.\n";
