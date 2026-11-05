<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/unit_sell_out_targets.php';
require __DIR__.'/../includes/ethical_source_template.php';
function epcheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
function epreject($fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Invalid product input accepted');}
foreach([2025,2026,2027] as $year){
 $period=$year.'-09';$b=buildEthicalSourceTemplate($period);$s=$b->getSheet(0);$blocks=ethicalProductBlocks();epcheck($s->getCell('B7')->getValue()==='NAMA PRODUK','Product code column removed');
 epcheck(array_map(fn($block)=>$block['last']-$block['first']+1,array_values($blocks))===[100,50,50,50],'Requested product capacities');
 epcheck($s->getCell('E7')->getValue()==='JANUARI'&&$s->getCell('E8')->getValue()==='TARGET'&&$s->getCell('G8')->getValue()==='REAL'&&$s->getCell('E9')->getValue()==='UNIT'&&$s->getCell('F9')->getValue()==='HNA'&&$s->getCell('G9')->getValue()==='UNIT'&&$s->getCell('H9')->getValue()==='HNA','Paired month headers');
 epreject(fn()=>parseEthicalSourceTemplate($b,$period));
 foreach($blocks as $kind=>$block){$r=$block['first'];$s->setCellValue('B'.$r,'Synthetic A');$s->setCellValue('B'.($r+1),'Synthetic B');$s->setCellValue('E'.$r,10);$s->setCellValue('D'.$r,100);$s->setCellValue('C'.$r,'Synthetic category');$s->setCellValue('D'.($r+1),100);$s->setCellValue('E'.($r+1),2);$s->setCellValue('I'.$r,0);$s->setCellValue('G'.$r,7);}
 epcheck($s->getCell('F10')->getValue()==='=D10*E10'&&$s->getCell('H10')->getValue()==='=D10*G10','Simple product HNA multiplication');
 $s->setCellValue('Y118',-3);$s->setCellValue('B118','Synthetic correction');$s->setCellValue('D118',100);
 $data=parseEthicalSourceTemplate($b,$period);
 epcheck($data['summary']['unit']['existing'][0]===24.0&&$data['summary']['hna']['new'][0]===2400.0,'Four product subtotals without scale changes');
 epcheck($data['summary']['unit']['existing'][1]===0.0&&$data['summary']['hna']['existing'][1]===0.0,'Zero and blank distinct');
 epcheck($s->getCell('E110')->getValue()==='=SUM(E10:E109)'&&(float)$s->getCell('E301')->getCalculatedValue()===24.0,'Simple subtotal and summary formulas');
 $s->setCellValue('E110',99999);epcheck(parseEthicalSourceTemplate($b,$period)===$data,'Ignore edited/cached subtotal');
 epcheck($data['inputs']['unit']['streamline'][5]===-3.0&&$data['inputs']['hna']['streamline'][5]===-300.0,'Negative correction accepted and HNA recomputed');
 epcheck($data['summary']['actual_unit']['existing'][0]===14.0&&$data['summary']['actual_hna']['new'][0]===1400.0,'REAL separate from TARGET');
 $actual=mergeEthicalSourceActuals([['year'=>$year,'actual'=>[123]]],[['report_year'=>$year,'payload'=>json_encode($data['inputs'])]]);epcheck($actual[0]['unitActualExisting'][0]===14.0&&$actual[0]['hnaActualNew'][0]===1400.0&&$actual[0]['actual']===[123],'API real fields do not overwrite HJP');
 $history=['report_year'=>$year,'payload'=>json_encode($data['inputs'])];$reuse=buildEthicalSourceWithHistory($year.'-12',$history);epcheck(parseEthicalSourceTemplate($reuse,$year.'-12')===$data,'Product names and values survive reuse');$reuse->disconnectWorksheets();
 foreach([$year.'-01',($year+1).'-02'] as $period2){$empty=buildEthicalSourceWithHistory($period2,$history);epcheck($empty->getSheet(0)->getCell('B10')->getValue()===null,'January reset and year isolation');$empty->disconnectWorksheets();}
 $legacy=$data['inputs'];unset($legacy['_products']);$old=buildEthicalSourceWithHistory($year.'-12',['report_year'=>$year,'payload'=>json_encode($legacy)]);epcheck(parseEthicalSourceTemplate($old,$year.'-12')['summary']===$data['summary'],'Old saved subtotal migration preserves values');$old->disconnectWorksheets();
 if($year===2026)foreach(['Xlsx','Ods'] as $format){$path=tempnam('/tmp','ethical-products-');try{$w=\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($b,$format);$w->setPreCalculateFormulas(false);$w->save($path);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);epcheck(parseEthicalSourceTemplate($loaded,$period)===$data,'Xlsx/Ods roundtrip');$loaded->disconnectWorksheets();}finally{unlink($path);}}
 epreject(fn()=>parseEthicalSourceTemplate($b,($year+1).'-09'));
 $s->setCellValue('B11','Synthetic A');epreject(fn()=>parseEthicalSourceTemplate($b,$period));$s->setCellValue('B11','Synthetic B');$s->setCellValue('B10',null);epreject(fn()=>parseEthicalSourceTemplate($b,$period));$s->setCellValue('B10','Synthetic A');$s->setCellValue('D10',-1);epreject(fn()=>parseEthicalSourceTemplate($b,$period));$b->disconnectWorksheets();
}
echo "PASS product UNIT/HNA pairs, four subtotals, simple formulas, blank/zero, duplicate and missing names, year isolation, history and XLSX/ODS uploads\n";
