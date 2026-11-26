<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/unit_sell_out_targets.php';
require __DIR__.'/../includes/ethical_source_template.php';
function verifyEthicalConclusion($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([2026,2027] as $year){
 $period=$year.'-10';$b=buildEthicalSourceTemplate($period,array_fill_keys(['existing','streamline','np_previous','np_current'],2));$s=$b->getSheetByName('PRODUK NAS');
 foreach($s->getCellCollection()->getCoordinates() as $address)verifyEthicalConclusion(!str_starts_with((string)$s->getCell($address)->getValue(),'RINGKASAN '),'No duplicated old summary in product sheet');
 $layout=locateEthicalProductTables($s,$year);$block=$layout['existing'];$r=$block['first'];
 foreach([$block['columns']['name']=>'Dummy first',$block['columns']['price']=>100,$block['columns']['months'][0]['unit']=>2,$block['columns']['months'][0]['actual_unit']=>1] as $col=>$value)$s->setCellValue([$col,$r],$value);
 \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($b)->clearCalculationCache();$c=$b->getSheetByName('REKAP');
 verifyEthicalConclusion((float)$c->getCell('B6')->getCalculatedValue()===2.0,'Target units');
 verifyEthicalConclusion((float)$c->getCell('B17')->getCalculatedValue()===200.0,'Target HNA');
 verifyEthicalConclusion((float)$c->getCell('B28')->getCalculatedValue()===1.0,'Actual units');
 verifyEthicalConclusion((float)$c->getCell('B39')->getCalculatedValue()===100.0,'Actual HNA');
 $s->insertNewRowBefore($block['total'],40);$layout=locateEthicalProductTables($s,$year);$block=$layout['existing'];
 for($i=0;$i<40;$i++){$r=$block['first']+2+$i;foreach([$block['columns']['name']=>'Dummy added '.$i,$block['columns']['price']=>10,$block['columns']['months'][0]['unit']=>3] as $col=>$v)$s->setCellValue([$col,$r],$v);}
 refreshEthicalConclusion($b,$period);\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($b)->clearCalculationCache();$c=$b->getSheetByName('REKAP');
 verifyEthicalConclusion((float)$c->getCell('B6')->getCalculatedValue()===122.0,'Forty added rows in summary');
 verifyEthicalConclusion((float)$c->getCell('B17')->getCalculatedValue()===1400.0,'Inserted rows without HNA formulas calculated from price');
 $s->removeRow($block['first']+2,10);$s->insertNewRowBefore(1,3);$s->insertNewColumnBefore('D');refreshEthicalConclusion($b,$period);\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($b)->clearCalculationCache();$c=$b->getSheetByName('REKAP');
 verifyEthicalConclusion((float)$c->getCell('B6')->getCalculatedValue()===92.0,'Deleted rows, moved headers/columns');
 $data=parseEthicalSourceTemplate($b,$period);verifyEthicalConclusion($data['inputs']['unit']['existing'][0]===92.0,'Upload agrees with conclusion');verifyEthicalConclusion($data['inputs']['hna']['existing'][0]===1100.0,'Uploaded HNA agrees');
 $next=buildEthicalSourceWithHistory($year.'-11',['report_year'=>$year,'payload'=>json_encode($data['inputs'])]);verifyEthicalConclusion($next->getSheetByName('REKAP')!==null,'History summary rebuilt');verifyEthicalConclusion(parseEthicalSourceTemplate($next,$year.'-11')['summary']===$data['summary'],'Stored records preserved');
 foreach(['Xlsx','Ods'] as $format){$path=tempnam('/tmp','dummy-ethical-summary-');try{$writer=\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($b,$format);$writer->setPreCalculateFormulas(false);$writer->save($path);$read=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);verifyEthicalConclusion($read->getSheetByName('REKAP')!==null,'Summary exported');verifyEthicalConclusion(parseEthicalSourceTemplate($read,$period)===$data,'Product upload roundtrip');$read->disconnectWorksheets();}finally{unlink($path);}}
 $next->disconnectWorksheets();$b->disconnectWorksheets();
}
echo "PASS dummy ETHICAL conclusion: moved summary, four metrics, added/deleted product rows, shifted headers/columns, HNA from price, history, XLSX/ODS and year isolation. No user workbook or DB accessed.\n";
