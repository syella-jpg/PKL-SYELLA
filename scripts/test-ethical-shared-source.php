<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/report_input_templates.php';require __DIR__.'/../includes/unit_sell_out_targets.php';require __DIR__.'/../includes/ethical_source_template.php';
use PhpOffice\PhpSpreadsheet\IOFactory;use PhpOffice\PhpSpreadsheet\Spreadsheet;
function checkEthicalSource($test,$message){if(!$test)throw new RuntimeException($message);}
function rejectEthicalSource(callable $fn,$message){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $period=$year.'-09';$book=buildEthicalSubtotalTemplate($period);$s=$book->getSheetByName('PRODUK NAS');
 checkEthicalSource($book->getSheetCount()===2&&$s->getHighestDataRow()===29,'One compact source with two metric tables');
 rejectEthicalSource(fn()=>parseEthicalSourceTemplate($book,$period),'Empty template accepted');
 foreach(ethicalSourceRows() as $metric=>$rows)foreach($rows as $kind=>$r)for($m=0;$m<12;$m++){
  $v=($metric==='unit'?1:1000)*(array_search($kind,['existing','streamline','np_previous','np_current'])+1)*($m+1);$s->setCellValue([$m+2,$r],$v);
 }
 $s->setCellValue('C9',null);$s->setCellValue('B10',0);$s->setCellValue('D10',null);$s->setCellValue('D11',null);
 $data=parseEthicalSourceTemplate($book,$period);
 checkEthicalSource($data['summary']['unit']['existing'][0]===3.0&&$data['summary']['unit']['existing'][1]===2.0,'EXISTING = existing+streamline; partial blank preserved');
 checkEthicalSource($data['summary']['unit']['new'][0]===4.0&&$data['summary']['unit']['new'][2]===null,'NP = previous+current; zero and unknown differ');
 checkEthicalSource($data['summary']['hna']['existing'][0]===3000.0&&$data['summary']['hna']['new'][0]===7000.0,'HNA independent from UNIT, no unrequested scaling');
 checkEthicalSource($s->getCell('B13')->getValue()==='=B8+B9'&&$s->getCell('B14')->getValue()==='=B10+B11'&&(float)$s->getCell('B27')->getCalculatedValue()===3000.0,'Visible simple summary formulas');
 foreach(['Xlsx','Ods'] as $format){$path=tempnam('/tmp','ethical-shared-');try{$writer=IOFactory::createWriter($book,$format);if($format==='Xlsx')$writer->setPreCalculateFormulas(false)->setForceFullCalc(true);$writer->save($path);$loaded=IOFactory::load($path);checkEthicalSource(parseEthicalSourceTemplate($loaded,$period)===$data,'Filled source download -> upload roundtrip');$loaded->disconnectWorksheets();}finally{unlink($path);}}
 $s->setCellValue('B13',999999);checkEthicalSource(parseEthicalSourceTemplate($book,$period)===$data,'Summaries must not be counted as extra inputs');
 $history=['report_year'=>$year,'payload'=>json_encode($data['inputs'])];$next=buildEthicalSourceWithHistory($year.'-12',$history);checkEthicalSource(array_intersect_key(parseEthicalSourceTemplate($next,$year.'-12')['summary'],$data['summary'])===$data['summary'],'Same-year reuse preserves four subtotals per metric');
 foreach([$year.'-01',($year+1).'-02'] as $freshPeriod){$fresh=buildEthicalSourceWithHistory($freshPeriod,$history);checkEthicalSource($fresh->getSheetByName('PRODUK NAS')->getCell('C9')->getValue()===null,'January reset/year isolation');$fresh->disconnectWorksheets();}
 rejectEthicalSource(fn()=>parseEthicalSourceTemplate($book,($year+1).'-09'),'Wrong year accepted');$s->setCellValue('B8','invalid');rejectEthicalSource(fn()=>parseEthicalSourceTemplate($book,$period),'Invalid UNIT accepted');
 $book->disconnectWorksheets();$next->disconnectWorksheets();
}
// Legacy NAS uses TARGET only; derived sheets and unrelated old NP cohorts must not double-count.
$b=new Spreadsheet();$s=$b->getSheet(0);$s->setTitle('PRODUK NAS');$labels=['TOTAL EXISTING','TOTAL STREAMLINE','TOTAL NP 2025','TOTAL NP 2026','TOTAL NP 2024'];foreach($labels as $i=>$label)$s->setCellValue('B'.($i+4),$label);
$monthNames=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
foreach($monthNames as $m=>$name){$base=4+$m*6;$s->setCellValue([$base,1],$name);$s->setCellValue([$base,2],'TARGET');$s->setCellValue([$base+3,2],'REAL');$s->setCellValue([$base,3],'UNIT');$s->setCellValue([$base+1,3],'HNA');$s->setCellValue([$base+2,3],'HJP');
 foreach($labels as $i=>$label){$s->setCellValue([$base,$i+4],$i+1);$s->setCellValue([$base+1,$i+4],($i+1)*1000);$s->setCellValue([$base+2,$i+4],999999);$s->setCellValue([$base+3,$i+4],999999);}}
$derived=$b->createSheet();$derived->setTitle('PRODUK PID');$derived->setCellValue('D4',999999);
$data=parseEthicalNasSource($b,'2026-09');checkEthicalSource($data['summary']['unit']['existing'][0]===3.0&&$data['summary']['unit']['new'][0]===7.0&&$data['summary']['hna']['new'][0]===7000.0,'Legacy master-only TARGET UNIT/HNA exact subtotal mapping');
$old=[['year'=>2025,'target'=>array_fill(0,12,91.0),'actual'=>array_fill(0,12,71.0),'hnaExisting'=>array_fill(0,12,2.0),'hnaNew'=>array_fill(0,12,3.0)],['year'=>2026,'target'=>array_fill(0,12,92.0),'actual'=>array_fill(0,12,72.0),'hnaExisting'=>array_fill(0,12,4.0),'hnaNew'=>array_fill(0,12,5.0)]];
$source=[];foreach(['existing','new'] as $kind)for($m=1;$m<=12;$m++)$source[]=['report_year'=>2026,'kind'=>$kind,'month'=>$m,'unit_target'=>$data['summary']['unit'][$kind][$m-1],'hna_target'=>$data['summary']['hna'][$kind][$m-1]];
$merged=mergeEthicalSourceData($old,$source);checkEthicalSource($merged[0]['hnaExisting']===$old[0]['hnaExisting']&&$merged[1]['target']===$old[1]['target']&&$merged[1]['actual']===$old[1]['actual'],'Other years and prior HJP/Actual values preserved');checkEthicalSource($merged[1]['hnaExisting'][0]===3000.0&&$merged[1]['hnaNew'][0]===7000.0,'HNA feeds ETHICAL API correctly');
if(in_array('--json',$argv,true))echo json_encode($data,JSON_THROW_ON_ERROR);
else echo "PASS Ethical shared source: four subtotals, UNIT/HNA separation, monthly Existing/NP math, dynamic years, XLSX/ODS, reused inputs, January reset, master NAS only, HJP/REAL ignored and legacy API values preserved\n";
