<?php
require __DIR__.'/../includes/tm_bod_split_analysis.php';
function checkSplit($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([2026,2027] as $year){
    $template=['slides'=>[
        ['id'=>'selling-in-'.$year,'subtitle'=>'KX Revenue Selling In','enabled'=>true],
        ['id'=>'selling-out-'.$year,'subtitle'=>'KX Revenue Selling Out','enabled'=>true],
        ['id'=>'rp-decoy','subtitle'=>'KX Revenue Selling Out (Rp)','enabled'=>true]
    ]];
    foreach(["SELL OUT:\nDummy outbound\n\nSELL IN:\nDummy inbound", "sell in: Dummy inbound\r\n\r\nsell out: Dummy outbound"] as $text){
        $parts=tmBodSplitAchievementAnalysis($template,$text);checkSplit(count($parts)===2,'Two parts');
        foreach($parts as $part){
            $out=$part['split_section']==='SELL OUT';
            checkSplit($part['destination_ids']===[$out?'selling-in-'.$year:'selling-out-'.$year],'Confirmed crossed destinations');
            checkSplit(str_contains($part['analysis_text'],$out?'Dummy outbound':'Dummy inbound'),'Only matching content');
            checkSplit(!str_contains($part['analysis_text'],$out?'Dummy inbound':'Dummy outbound'),'No content leakage');
        }
        $row=['source_note_id'=>'dummy-source','report_period'=>$year.'-09','sent_by'=>'dummy','analysis_text'=>json_encode(['tm_bod_split'=>1,'parts'=>$parts])];
        $expanded=tmBodExpandSplitTransfer($row);checkSplit(count($expanded)===2,'Stored envelope expands to two incoming notes');
        foreach($expanded as $part){checkSplit($part['source_note_id']==='dummy-source','Source identity retained');checkSplit($part['report_period']===$year.'-09','Period retained');}
        for($month=1;$month<=12;$month++)checkSplit(tmBodQuarterForPeriod(sprintf('%d-%02d',$year,$month))==='Q'.(int)ceil($month/3),'Quarter matches period');
    }
    foreach(['unlabelled','SELL OUT: only','SELL OUT:\nSELL IN:','SELL OUT: a'."\nSELL OUT: b",'Preface'."\nSELL OUT: a\nSELL IN: b"] as $bad){
        try{tmBodSplitAchievementAnalysis($template,$bad);throw new LogicException('Invalid headings accepted');}catch(InvalidArgumentException $e){}
    }
    $template['slides'][0]['enabled']=false;
    try{tmBodSplitAchievementAnalysis($template,"SELL OUT: a\nSELL IN: b");throw new LogicException('Disabled target accepted');}catch(InvalidArgumentException $e){}
}
$normal=['analysis_text'=>'Ordinary note'];checkSplit(tmBodExpandSplitTransfer($normal)===[$normal],'Existing transfers preserved');
echo "PASS dummy split analysis: confirmed crossed destinations, separate content, envelope roundtrip, period/year/quarter, invalid/empty headings and disabled target. No live data accessed.\n";
