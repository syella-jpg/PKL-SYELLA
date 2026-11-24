<?php
require __DIR__.'/../includes/tm_bod_split_analysis.php';
function verifySo($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([2026,2027] as $year){
    $targets=['Pharma'=>'Revenue Divisi Farma (Rp)','HCE'=>'HEC Revenue (Rp)','Ethical'=>'Ethical Revenue Selling Out (Rp)','GB 5'=>'GB 5 Revenue (Rp)','GB 6'=>'GB 6 Revenue (Rp)'];
    $template=['slides'=>[],'routes'=>['Revenue Konimex Selling Out (RP)|Pharma'=>['wrong-route']]];
    foreach($targets as $name=>$title)$template['slides'][]=['id'=>$year.'-'.$name,'subtitle'=>$title,'enabled'=>true];
    foreach(['Pharma','HCE','Ethical'] as $tab)verifySo(tmBodAnalysisTargetIds($template,'Revenue Konimex Selling Out (RP)',$tab)===[$year.'-'.$tab],'Exact requested destination');
    foreach(["GB 5:\nDummy five\n\nGB 6:\nDummy six","gb6: Dummy six\r\ngb5: Dummy five"] as $text){
        $parts=tmBodSplitFoodAnalysis($template,$text);verifySo(count($parts)===2,'Two Food parts');
        foreach($parts as $part){
            $five=$part['split_section']==='GB 5';
            verifySo($part['destination_ids']===[$year.'-'.$part['split_section']],'Own slide ID');
            verifySo(str_contains($part['analysis_text'],$five?'Dummy five':'Dummy six'),'Own text');
            verifySo(!str_contains($part['analysis_text'],$five?'Dummy six':'Dummy five'),'No other GB text');
        }
        $row=['source_note_id'=>'dummy','report_period'=>$year.'-10','analysis_text'=>json_encode(['tm_bod_split'=>1,'parts'=>$parts])];
        $notes=tmBodExpandSplitTransfer($row);verifySo(count($notes)===2,'Both Food parts expanded');
        foreach($notes as $note)verifySo($note['report_period']===$year.'-10','Period preserved');
        verifySo(tmBodQuarterForPeriod($year.'-10')==='Q4','Correct quarter');
    }
    foreach(["GB 5: a","GB 5:\nGB 6: b","GB 5: a\nGB 5: b","Preface\nGB 5: a\nGB 6: b"] as $bad){
        try{tmBodSplitFoodAnalysis($template,$bad);throw new LogicException('Invalid Food accepted');}catch(InvalidArgumentException $e){}
    }
    $template['slides'][3]['enabled']=false;
    try{tmBodSplitFoodAnalysis($template,"GB 5: a\nGB 6: b");throw new LogicException('Disabled GB accepted');}catch(InvalidArgumentException $e){}
}
echo "PASS dummy Revenue SO: Pharma/HCE/Ethical exact annual destinations, Food GB5/6 text isolation, stored split roundtrip, quarter, invalid/empty/disabled guards. No live data accessed.\n";
