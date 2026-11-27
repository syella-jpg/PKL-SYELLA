<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/report_input_templates.php';require __DIR__.'/../includes/raw_template_protection.php';require __DIR__.'/../includes/near_ed_input_template.php';
function nearCheck($ok,$text){if(!$ok)throw new RuntimeException($text);}
$b=buildNearEdInputTemplate('2026-11',14);$expected=[];
foreach(nearEdGroups() as $g=>$group){
 $s=$b->getSheetByName($group);nearCheck($s!==null,'Seven group input sheets');
 $count=$g===0?13:($group==='ETHICAL'?11:3);$products=[];
 for($i=0;$i<$count;$i++){$row=6+$i;$name=$group.' Dummy '.($i+1);$s->setCellValue('A'.$row,'D'.($i+1));$s->setCellValue('B'.$row,$name);$products[]=['name'=>$name,'row'=>$row];$stock=0;$rupiah=0;
  foreach(nearEdBuckets() as $bucket=>[$label,$unit,$rp]){$value=($bucket%2===0?($count-$i):($i+1))*100;if($g===0&&$i===1&&$bucket===0)$value=$count*100;$s->setCellValue($unit.$row,$i+1);$s->setCellValue($rp.$row,$value);$stock+=$i+1;$rupiah+=$value;$products[$i]['values'][$bucket]=$value;}
  $s->setCellValue('E'.$row,2);$s->setCellValue('F'.$row,$stock);$s->setCellValue('I'.$row,$rupiah);
 }
 // An unnamed row must not outrank actual products.
 $s->setCellValue('K19',999999);if($group==='GB 1')$s->setCellValue('B19',null);
 foreach(nearEdBuckets() as $bucket=>$unused){$ordered=$products;usort($ordered,static fn($a,$c)=>($c['values'][$bucket]<=>$a['values'][$bucket])?:($a['row']<=>$c['row']));$expected[$group][$bucket]=array_column(array_slice($ordered,0,10),'name');}
 nearCheck($s->getProtection()->getSheet(),'Input derived columns protected');nearCheck($s->getStyle('K6')->getProtection()->getLocked()==='unprotected','Bucket inputs editable');
}
nearCheck($b->getSheetByName('NEAR ED')!==null&&$b->getSheetByName('TOP 10 NEAR ED')!==null,'Quarter-free names');
nearCheck($b->getSheetByName('NEAR ED TW 4')===null,'Old quarter sheet absent');
addRawTemplateInstructions($b);protectRawTemplate($b,'2026-11','NEAR ED');
$p='/tmp/dummy-near-ed-regression.xlsx';$w=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($b);$w->setPreCalculateFormulas(false)->setForceFullCalc(true);$w->save($p);file_put_contents('/tmp/dummy-near-ed-expected.json',json_encode($expected));$b->disconnectWorksheets();
$blank=buildReportInputTemplate('NEAR ED','2027-01');foreach(nearEdGroups() as $group){$s=$blank->getSheetByName($group);nearCheck($s->getCell('B6')->getValue()===null&&$s->getCell('K6')->getValue()===null,'Fresh template has no copied records');}$blank->disconnectWorksheets();
echo "PASS NEAR ED template structure, seven groups, quarter-free names, editable inputs, protected formulas and empty initial data. Dummy formula fixture written to /tmp for Calc verification.\n";
