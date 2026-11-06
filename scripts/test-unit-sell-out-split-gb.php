<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/unit_sell_out_targets.php';
function splitCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $b=buildUnitSellOutLegacyProductTemplate($year.'-09',4,2);splitCheck($b->getSheetNames()===['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ETHICAL','IB','_TEMPLATE'],'Separate GB sheets');
 foreach(range(1,6) as $gb){$s=$b->getSheetByName('GB '.$gb);splitCheck($s->getCell('A6')->getValue()===$gb,'Automatic GB');foreach(['A','B','C','D'] as $i=>$cat){$r=6+$i;$s->setCellValue('C'.$r,'Synthetic '.$cat);$s->setCellValue('D'.$r,$cat);$s->setCellValue('F'.$r,($i+1)*$gb);$s->setCellValue('Q'.$r,0);}splitCheck($s->getCell('A14')->getValue()==='EXIST GB '.$gb,'Summary belongs to this GB only');splitCheck((float)$s->getCell('F14')->getCalculatedValue()===6.0*$gb,'Existing A+B+C formula');}
 $b->getSheetByName('ETHICAL')->setCellValue('C6',9);$b->getSheetByName('IB')->setCellValue('C6',10);$b->getSheetByName('IB')->setCellValue('C7',2);
 $data=parseUnitSellOutInputTemplate($b,$year);foreach(range(1,6) as $gb){$rows=array_values(array_filter($data['records'],fn($r)=>$r['product']==='gb'.$gb&&$r['month']===1));splitCheck($rows[0]['units']===number_format($gb*6,6,'.','')&&$rows[1]['units']===number_format($gb*4,6,'.',''),'Each sheet mapped to correct GB');}
 foreach(['Xlsx','Ods'] as $format){$path=tempnam('/tmp','split-gb-');try{$w=\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($b,$format);$w->setPreCalculateFormulas(false);$w->save($path);$loaded=readUnitSellOutTargetBook($path);splitCheck(parseUnitSellOutInputTemplate($loaded,$year)===$data,'XLSX/ODS load filter and parser');$loaded->disconnectWorksheets();}finally{unlink($path);}}
 $history=[];foreach($data['inputs'] as $product=>$parts)$history[]=['report_year'=>$year,'product'=>$product,'payload'=>json_encode($parts)];
 $next=buildUnitSellOutLegacyProductTemplate($year.'-12',4,2);fillUnitSellOutInputHistory($next,$year.'-12',$history);splitCheck(parseUnitSellOutInputTemplate($next,$year)===$data,'History restored per GB');$next->disconnectWorksheets();
 $s=$b->getSheetByName('GB 2');$s->setCellValue('A6',1);try{parseUnitSellOutInputTemplate($b,$year);throw new RuntimeException('Wrong GB accepted');}catch(InvalidArgumentException $expected){}$b->disconnectWorksheets();
}
echo "PASS six GB sheets, automatic identities, A/B/C/D sums, Ethical/IB, XLSX/ODS upload, history and wrong-GB rejection\n";
