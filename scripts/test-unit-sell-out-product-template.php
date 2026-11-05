<?php
// Synthetic product names and figures only; never reads private data or writes production DB.
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/report_input_templates.php';require __DIR__.'/../includes/unit_sell_out_targets.php';
use PhpOffice\PhpSpreadsheet\IOFactory;use PhpOffice\PhpSpreadsheet\Cell\DataType;
function checkProductTarget($test,$message){if(!$test)throw new RuntimeException($message);}
function rejectProductTarget(callable $fn,$message){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $b=buildUnitSellOutProductTemplate($year.'-09',30,4,false);$g=$b->getSheetByName('GB 1-6');$e=$b->getSheetByName('ETHICAL');$ib=$b->getSheetByName('IB');
 checkProductTarget($b->getSheetNames()===['GB 1-6','ETHICAL','IB','_TEMPLATE']&&$b->getSheetByName('_TEMPLATE')->getSheetState()==='hidden','Three visible sheets');
 rejectProductTarget(fn()=>parseUnitSellOutInputTemplate($b,$year),'Blank template accepted');
 $r=6;
 for($gb=1;$gb<=6;$gb++)foreach(['A','B','C','D'] as $i=>$category){
  foreach(['A'=>(string)$gb,'B'=>'000'.$gb.$category,'C'=>'Synthetic GB '.$gb.' '.$category,'D'=>$category,'E'=>'PCS'] as $c=>$v)$g->getCell($c.$r)->setValueExplicit($v,DataType::TYPE_STRING);
  for($m=1;$m<=12;$m++)$g->setCellValue([$m+5,$r],$gb*100+$i*10+$m);$r++;
 }
 foreach([6=>['KAT E','000ET','Synthetic Ethical Existing'],7=>['NP','001ET','Synthetic Ethical New']] as $r=>[$cat,$code,$name]){
  $e->setCellValue('D'.$r,$cat);$e->getCell('B'.$r)->setValueExplicit($code,DataType::TYPE_STRING);$e->setCellValue('C'.$r,$name);
  for($m=1;$m<=12;$m++)$e->setCellValue([$m+5,$r],$m*($r===6?7:3));
 }
 $g->setCellValue('F10',null);$g->setCellValue('F12',null);$g->setCellValue('G13',0);$e->setCellValue('H7',null);
 for($m=1;$m<=12;$m++){$ib->setCellValue([$m+2,6],10*$m*$m);$ib->setCellValue([$m+2,7],2*$m);}$ib->setCellValue('H7',null);
 $data=parseUnitSellOutInputTemplate($b,$year);$lookup=[];foreach($data['records'] as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
 checkProductTarget(count($data['records'])===180&&count($data['inputs']['gb1']['rows'])===4,'All eight groups and product details read');
 checkProductTarget($lookup['gb1|existing|1']==='333.000000'&&$lookup['gb1|new|1']==='131.000000','GB existing/new from product categories');
 checkProductTarget($lookup['gb2|existing|1']==='211.000000'&&$lookup['gb2|new|2']==='0.000000','Incomplete categories and zero preserved');
 checkProductTarget($lookup['ethical|existing|3']==='21.000000'&&$lookup['ethical|new|3']===null,'Ethical Existing-only works with KAT E and empty NP');
 checkProductTarget($lookup['ib|total|1']==='12.000000'&&$lookup['ib|total|2']==='32.000000'&&$lookup['ib|total|6']===null&&$lookup['ib|total|7']===null,'Two cumulative IB KPIs retain monthly difference and gaps');
 $find=function($sheet,$label){foreach($sheet->getCoordinates() as $address)if($sheet->getCell($address)->getValue()===$label)return $sheet->getCell($address)->getRow();throw new RuntimeException('Missing summary '.$label);};
 $summary=$find($g,'EXIST GB 1');checkProductTarget((float)$g->getCell('F'.$summary)->getCalculatedValue()===333.0,'GB visible SUMIFS');
 checkProductTarget((float)$g->getCell('R'.$summary)->getCalculatedValue()===3105.0,'Visible YTD selected month');
 $eth=$find($e,'EXIST ETHICAL');checkProductTarget((float)$e->getCell('F'.$eth)->getCalculatedValue()===7.0,'Ethical KAT E existing formula');
 checkProductTarget($ib->getCell('D9')->getValue()==='=D8-C8'&&(float)$ib->getCell('D9')->getCalculatedValue()===32.0,'IB visible simple difference formula');
 foreach(['Xlsx','Ods'] as $format){$path=tempnam('/tmp','product-unit-target-');try{
  $writer=IOFactory::createWriter($b,$format);if($format==='Xlsx')$writer->setPreCalculateFormulas(false)->setForceFullCalc(true);$writer->save($path);$loaded=readUnitSellOutTargetBook($path);
  checkProductTarget(parseUnitSellOutInputTemplate($loaded,$year)===$data,$format.' filtered upload must preserve all products and leading-zero codes');
  checkProductTarget((float)$loaded->getSheetByName('GB 1-6')->getCell('F'.$summary)->getCalculatedValue()===333.0,'Visible summaries recalculate after download');
  if($format==='Xlsx'){$zip=new ZipArchive();$zip->open($path);$xml=$zip->getFromName('xl/workbook.xml');checkProductTarget(str_contains($xml,'calcMode="auto"')&&str_contains($xml,'fullCalcOnLoad="1"'),'Excel must auto-recalculate formulas');$zip->close();}
  rejectProductTarget(fn()=>parseUnitSellOutInputTemplate($loaded,$year+1),'Year mismatch accepted');$loaded->disconnectWorksheets();
 }finally{unlink($path);}}
 $g->setCellValue('F'.$summary,99999);$ib->setCellValue('C9',99999);checkProductTarget(parseUnitSellOutInputTemplate($b,$year)===$data,'Display summaries must not be double-counted');
 $history=[];foreach($data['inputs'] as $product=>$parts)$history[]=['report_year'=>$year,'product'=>$product,'payload'=>json_encode($parts)];
 $next=buildUnitSellOutProductTemplate($year.'-12',30,4,false);fillUnitSellOutInputHistory($next,$year.'-12',$history);
 checkProductTarget(parseUnitSellOutInputTemplate($next,$year)===$data,'Names/codes/categories/months and IB reused exactly');
 foreach([$year.'-01',($year+1).'-02'] as $period){$fresh=buildUnitSellOutProductTemplate($period,30,4,false);fillUnitSellOutInputHistory($fresh,$period,$history);checkProductTarget($fresh->getSheetByName('GB 1-6')->getCell('C6')->getValue()===null,'January reset and year isolation');$fresh->disconnectWorksheets();}
 $g->setCellValue('B7','0001A');rejectProductTarget(fn()=>parseUnitSellOutInputTemplate($b,$year),'Duplicate product code accepted');$g->setCellValue('B7','0001B');
 $g->setCellValue('C6',null);rejectProductTarget(fn()=>parseUnitSellOutInputTemplate($b,$year),'Missing product name accepted');$g->setCellValue('C6','Synthetic GB 1 A');
 $g->setCellValue('F6','invalid');rejectProductTarget(fn()=>parseUnitSellOutInputTemplate($b,$year),'Invalid unit value accepted');$g->setCellValue('F6',101);
 $g->setCellValue('D6','X');rejectProductTarget(fn()=>parseUnitSellOutInputTemplate($b,$year),'Invalid product category accepted');$g->setCellValue('D6','A');
 $g->setCellValue('F5','FEBRUARI');rejectProductTarget(fn()=>parseUnitSellOutInputTemplate($b,$year),'Shifted months accepted');
 $b->disconnectWorksheets();$next->disconnectWorksheets();
}
// Full-capacity boundary is past the old 1,000-row read filter.
$large=buildReportInputTemplate('TARGET UNIT SELL OUT','2026-11');$s=$large->getSheetByName('GB 1-6');
foreach(['A1005'=>'6','B1005'=>'000LAST','C1005'=>'Synthetic final product','D1005'=>'D','Q1005'=>7] as $cell=>$value)$s->setCellValue($cell,$value);
$path=tempnam('/tmp','product-boundary-');try{IOFactory::createWriter($large,'Xlsx')->setPreCalculateFormulas(false)->setForceFullCalc(true)->save($path);$loaded=readUnitSellOutTargetBook($path);$parsed=parseUnitSellOutInputTemplate($loaded,2026);checkProductTarget($parsed['inputs']['gb6']['rows'][0]['code']==='000LAST'&&$parsed['inputs']['gb6']['rows'][0]['months'][11]===7.0,'Final product row lost by upload filter');$loaded->disconnectWorksheets();}finally{unlink($path);}$large->disconnectWorksheets();
if(in_array('--json',$argv,true))echo json_encode($data['records'],JSON_THROW_ON_ERROR);
else echo "PASS product-level three-sheet template, 2025/2026/2027, XLSX/ODS, all GB/Ethical/IB totals, name/code/zero preservation, cumulative gaps, formulas, history/January and final capacity row\n";
