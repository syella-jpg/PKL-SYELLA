<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/unit_sell_out_targets.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;use PhpOffice\PhpSpreadsheet\IOFactory;
function checkEthSummary($test,$message){if(!$test)throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $b=buildUnitSellOutProductTemplate($year.'-09',2,2);$e=$b->getSheetByName('ETHICAL');
 checkEthSummary($b->getSheetNames()===['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ETHICAL','IB','_TEMPLATE'],'Keep separate GB sheets plus Ethical and IB');
 checkEthSummary($e->getHighestDataRow()===8,'Ethical must contain only the compact used inputs, not product rows');
 checkEthSummary($e->getCell('A6')->getValue()==='EXISTING PRODUCT (UNIT)'&&$e->getCell('B6')->getValue()==='TARGET'&&$e->getCell('A7')->getValue()==='NEW PRODUCT (UNIT)','Exact GMEB target labels');
 $source=new Spreadsheet();$g=$source->getSheet(0);$g->setTitle('GMEB');$g->setCellValue('A1','TAHUN '.$year);$g->setCellValue('A2','1. PERSPEKTIF FINANCIAL');
 foreach(unitSellOutInputMonths() as $m=>$label)$g->setCellValue([$m+16,3],$label);
 $g->setCellValue('B4','Revenue Growth');$g->setCellValue('C4','Revenue Selling Out (Unit)');$g->setCellValue('D5','Existing Product (Unit)');$g->setCellValue('O5','Target');$g->setCellValue('D7','New Product (Unit)');$g->setCellValue('O7','Target');
 for($m=0;$m<12;$m++){
  $existing=($m+1)*7;$new=$m===1?null:($m===0?0:($m+1)*3);
  $e->setCellValue([$m+3,6],$existing);$e->setCellValue([$m+3,7],$new);
  $g->setCellValue([$m+16,5],$existing);$g->setCellValue([$m+16,7],$new);
 }
 $data=parseUnitSellOutInputTemplate($b,$year);$ethical=array_values(array_filter($data['records'],fn($r)=>$r['product']==='ethical'));
 checkEthSummary($ethical===parseUnitSellOutEthicalTargets($g,$year),'Compact Ethical must produce the same monthly records as GMEB source');
 checkEthSummary((float)$e->getCell('C8')->getCalculatedValue()===7.0&&(float)$e->getCell('D8')->getCalculatedValue()===14.0,'Existing-only and zero NP totals');
 checkEthSummary($e->getCell('E8')->getValue()==='=E6+E7','Visible total formula');
 foreach(['Xlsx','Ods'] as $format){$path=tempnam('/tmp','ethical-compact-');try{
  $writer=IOFactory::createWriter($b,$format);if($format==='Xlsx')$writer->setPreCalculateFormulas(false)->setForceFullCalc(true);$writer->save($path);
  $loaded=readUnitSellOutTargetBook($path);checkEthSummary(parseUnitSellOutInputTemplate($loaded,$year)===$data,'Compact ethical XLSX/ODS roundtrip');$loaded->disconnectWorksheets();
 }finally{unlink($path);}}
 $history=[];foreach($data['inputs'] as $product=>$parts)$history[]=['report_year'=>$year,'product'=>$product,'payload'=>json_encode($parts)];
 $next=buildUnitSellOutProductTemplate($year.'-12',2,2);fillUnitSellOutInputHistory($next,$year.'-12',$history);checkEthSummary(parseUnitSellOutInputTemplate($next,$year)===$data,'History preserves Ethical summary input exactly');
 $fresh=buildUnitSellOutProductTemplate($year.'-01',2,2);fillUnitSellOutInputHistory($fresh,$year.'-01',$history);checkEthSummary($fresh->getSheetByName('ETHICAL')->getCell('C6')->getValue()===null,'January reset');
 $e->setCellValue('C6',-1);try{parseUnitSellOutInputTemplate($b,$year);throw new RuntimeException('Negative Ethical accepted');}catch(InvalidArgumentException $expected){}
 $b->disconnectWorksheets();$source->disconnectWorksheets();$next->disconnectWorksheets();$fresh->disconnectWorksheets();
}
// Previous product-level Ethical history is aggregated once without losing values or zeros.
$b=buildUnitSellOutProductTemplate('2026-12',2,2);$history=[['report_year'=>2026,'product'=>'ethical','payload'=>json_encode(['__format'=>'products_v2','rows'=>[
 ['code'=>'001','name'=>'Synthetic A','category'=>'EXIST','unit'=>'PCS','months'=>[10,...array_fill(0,11,null)]],
 ['code'=>'002','name'=>'Synthetic B','category'=>'KAT E','unit'=>'PCS','months'=>[3,...array_fill(0,11,null)]],
 ['code'=>'003','name'=>'Synthetic C','category'=>'NP','unit'=>'PCS','months'=>[0,...array_fill(0,11,null)]]
]])]];
fillUnitSellOutInputHistory($b,'2026-12',$history);$parsed=parseUnitSellOutInputTemplate($b,2026);
checkEthSummary($parsed['inputs']['ethical']['existing'][0]===13.0&&$parsed['inputs']['ethical']['new'][0]===0.0,'Old Ethical product history is preserved in the compact summary');
echo "PASS compact ETHICAL matches GMEB TARGET Existing/New Unit only, 2025/2026/2027, XLSX/ODS, visible TOTAL, no product columns, blank/zero, history/January and old-format migration\n";
