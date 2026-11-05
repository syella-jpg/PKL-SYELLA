<?php
// Synthetic fixtures only: no private workbook contents or production DB writes.
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/unit_sell_out_targets.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function checkCombined($test,$message){if(!$test)throw new RuntimeException($message);}
function rejectCombined(callable $fn,$message){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
    $book=buildUnitSellOutSummaryTemplate($year.'-09');$sheet=$book->getSheetByName('TARGET UNIT SELL OUT');
    checkCombined($book->getSheetCount()===2&&$book->getSheetByName('_TEMPLATE')->getSheetState()==='hidden','One visible input sheet');
    foreach(unitSellOutInputRows() as $rows)foreach($rows as $r)for($m=0;$m<12;$m++)checkCombined($sheet->getCell([$m+3,$r])->getValue()===null,'Fresh editable inputs must be truly blank');
    rejectCombined(fn()=>parseUnitSellOutTargets($book,$year),'Blank template accepted');
    foreach(unitSellOutInputRows() as $product=>$rows)foreach($rows as $kind=>$r)for($m=1;$m<=12;$m++){
        if($product==='ib')$v=$kind==='A'?10*$m*$m:2*$m;
        elseif($product==='ethical')$v=$kind==='existing'?7*$m:3*$m;
        else{$gb=(int)substr($product,2);$v=$gb*100+array_search($kind,['A','B','C','D'])*10+$m;}
        $sheet->setCellValue([$m+2,$r],$v);
    }
    // GB2 has missing categories: available B still counts; explicit 0 remains numeric.
    $sheet->setCellValue('C15',null);$sheet->setCellValue('C17',null);$sheet->setCellValue('D18',0);
    $sheet->setCellValue('E60',null); // Ethical NP unavailable, Existing remains populated.
    $sheet->setCellValue('H69',null); // IB gap must not become an invented monthly amount.
    $data=parseUnitSellOutInputTemplate($book,$year);checkCombined(count($data['records'])===180,'One file must produce six GB, Ethical and IB');
    $lookup=[];foreach($data['records'] as $r)$lookup[$r['product'].'|'.$r['kind'].'|'.$r['month']]=$r['units'];
    checkCombined($lookup['gb1|existing|1']==='333.000000'&&$lookup['gb1|new|1']==='131.000000','GB Existing A+B+C vs NP D');
    checkCombined($lookup['gb2|existing|1']==='211.000000'&&$lookup['gb2|new|2']==='0.000000','Missing categories count zero; explicit zero retained');
    checkCombined($lookup['ethical|existing|3']==='21.000000'&&$lookup['ethical|new|3']===null,'Ethical blank NP does not block Existing');
    checkCombined($lookup['ib|total|1']==='12.000000'&&$lookup['ib|total|2']==='32.000000'&&$lookup['ib|total|12']==='232.000000','IB A+B first month and consecutive cumulative differences');
    checkCombined($lookup['ib|total|6']===null&&$lookup['ib|total|7']===null&&$lookup['ib|total|8']!==null,'IB missing cumulative component must not create a spike');
    checkCombined($sheet->getCell('C11')->getValue()==='=C7+C8+C9'&&$sheet->getCell('C13')->getValue()==='=C11+C12','Visible simple Existing and total formulas');
    checkCombined($sheet->getCell('D71')->getValue()==='=D70-C70'&&(float)$sheet->getCell('D71')->getCalculatedValue()===32.0,'IB visible monthly difference formula');
    checkCombined((float)$sheet->getCell('O11')->getCalculatedValue()===3105.0,'YTD uses selected month');
    foreach(['Xlsx','Ods'] as $format){
        $path=tempnam('/tmp','combined-unit-target-');
        try{
            IOFactory::createWriter($book,$format)->save($path);
            $loaded=readUnitSellOutTargetBook($path);
            checkCombined(parseUnitSellOutTargets($loaded,$year)===$data['records'],$format.' download -> filtered upload must preserve all groups/values');
            rejectCombined(fn()=>parseUnitSellOutTargets($loaded,$year+1),'Wrong year accepted');
            $loaded->disconnectWorksheets();
        }finally{unlink($path);}
    }
    // Summary cells are display formulas, never an alternative source to double-count.
    $sheet->setCellValue('C11',99999);$sheet->setCellValue('C71',99999);
    checkCombined(parseUnitSellOutTargets($book,$year)===$data['records'],'Stale summary cells must not overwrite validated inputs');
    $history=[];foreach($data['inputs'] as $product=>$parts)$history[]=['report_year'=>$year,'product'=>$product,'payload'=>json_encode($parts)];
    $next=buildUnitSellOutSummaryTemplate($year.'-12');fillUnitSellOutInputHistory($next,$year.'-12',$history);
    checkCombined(parseUnitSellOutTargets($next,$year)===$data['records'],'Same-year template reuse preserves categories and IB cumulative inputs');
    checkCombined($next->getSheetByName('TARGET UNIT SELL OUT')->getCell('D71')->isFormula(),'Reuse preserves visible formulas');
    foreach([$year.'-01',($year+1).'-02'] as $period){
        $fresh=buildUnitSellOutSummaryTemplate($period);fillUnitSellOutInputHistory($fresh,$period,$history);
        checkCombined($fresh->getSheetByName('TARGET UNIT SELL OUT')->getCell('C7')->getValue()===null,'January reset and year isolation');$fresh->disconnectWorksheets();
    }
    $sheet->setCellValue('C7','invalid');rejectCombined(fn()=>parseUnitSellOutTargets($book,$year),'Invalid text accepted');
    $sheet->setCellValue('C7',-1);rejectCombined(fn()=>parseUnitSellOutTargets($book,$year),'Negative target accepted');
    $sheet->setCellValue('C7',1);$sheet->setCellValue('C6','FEBRUARI');rejectCombined(fn()=>parseUnitSellOutTargets($book,$year),'Shifted months accepted');
    $book->disconnectWorksheets();$next->disconnectWorksheets();
}
if(in_array('--json',$argv,true))echo json_encode($data['records'],JSON_THROW_ON_ERROR);
else echo "PASS combined TARGET UNIT SELL OUT: 2025/2026/2027, all 180 monthly records, XLSX/ODS upload roundtrip, simple formulas/YTD, incomplete categories, Ethical Existing-only, IB cumulative gaps, stale summaries, same-year reuse, January reset and validation\n";
