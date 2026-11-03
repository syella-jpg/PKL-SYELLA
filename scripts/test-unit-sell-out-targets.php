<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/unit_sell_out_targets.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
function checkUnitTarget($test,$message){if(!$test)throw new RuntimeException($message);}
$book=new Spreadsheet();$book->removeSheetByIndex(0);
$months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGT','SEP','OKT','NOP','DES'];
for($gb=1;$gb<=6;$gb++){
 $s=$book->createSheet();$s->setTitle('TARGET GB '.($gb===3?'':'').$gb);$s->setCellValue('A1','TARGET UNIT TAHUN 2027');
 $first=$gb<=4?5:7;$label=in_array($gb,[1,3],true)?2:($gb<=4?1:3);
 foreach($months as $i=>$month)$s->setCellValue([$first+$i+1,2],$month);
 foreach(['A','B','C','D'] as $i=>$category){
  if($gb===5&&in_array($category,['A','C'],true))continue;
  $text=$gb<=4?'Kategori '.$category:'TOTAL KATEGORI '.$category;
  $s->setCellValue([$label+1,4+$i],$text);
  foreach($months as $m=>$month)$s->setCellValue([$first+$m+1,4+$i],($i+1)*10+$gb+$m);
 }
 if($gb>=5){$s->setCellValue([$label+1,8],'TOTAL BRAND KAT. B');$s->setCellValue([$first+1,8],99999);}
 $s->setCellValue([$label+1,10],'RUPIAH');$s->setCellValue([$label+1,11],'Kategori D');$s->setCellValue([$first+1,11],999999);
 $s->setCellValue([$first+1,7],'=2+3');
}
$budget=$book->createSheet();$budget->setTitle('BUDGET GB 1');$budget->setCellValue('F4',999999);
$records=parseUnitSellOutTargets($book,2027);checkUnitTarget(count($records)===144,'Wrong group/month/category count');
$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
for($gb=1;$gb<=6;$gb++){
 checkUnitTarget((float)$lookup['gb'.$gb.'|existing|1']===($gb===5?25.0:60.0+3*$gb),'A+B+C summary/double counting failed');
 checkUnitTarget((float)$lookup['gb'.$gb.'|new|1']===5.0,'D formula ignored or wrong source block');
 checkUnitTarget((float)$lookup['gb'.$gb.'|new|12']===51.0+$gb,'Monthly mapping failed');
}
try{parseUnitSellOutTargets($book,2026);throw new RuntimeException('Wrong year accepted');}catch(InvalidArgumentException $expected){}
$sheet=$book->getSheetByName('TARGET GB 1');$sheet->setCellValue('F4',null);$sheet->setCellValue('F5',null);$sheet->setCellValue('F6',null);$sheet->setCellValue('G7',0);
$records=parseUnitSellOutTargets($book,2027);$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];checkUnitTarget($lookup['gb1|existing|1']===null,'Blanks changed to numbers');checkUnitTarget($lookup['gb1|new|2']==='0.000000','Explicit zero lost');
// Missing A/B/C count as zero; available categories must still contribute.
$gb2=$book->getSheetByName('TARGET GB 2');
$gb2->setCellValue('B4',null);$gb2->setCellValue('B6',null);
$records=parseUnitSellOutTargets($book,2027);$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
checkUnitTarget($lookup['gb2|existing|1']==='22.000000','Missing A/C should be zero and B retained');
$gb2->setCellValue('F5',null);$gb2->setCellValue('G5',0);
$records=parseUnitSellOutTargets($book,2027);$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
checkUnitTarget($lookup['gb2|existing|1']==='0.000000','Missing categories with blank B should total zero');checkUnitTarget($lookup['gb2|existing|2']==='0.000000','Existing zero lost');
$gb2->setCellValue('B5',null);
$records=parseUnitSellOutTargets($book,2027);$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
for($m=1;$m<=12;$m++)checkUnitTarget($lookup['gb2|existing|'.$m]==='0.000000','All missing A/B/C must be zero');
checkUnitTarget($lookup['gb2|new|1']==='5.000000','D changed by missing ABC');
$gb2->setCellValue('B7',null);
$records=parseUnitSellOutTargets($book,2027);$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
for($m=1;$m<=12;$m++)checkUnitTarget($lookup['gb2|new|'.$m]==='0.000000','Missing D must produce zero NP');
$gb2->setCellValue('B7','Kategori D');
// Real upload has a leading column absent from the blank sample: categories move B -> C.
$gb2->setCellValue('B7',null);$gb2->setCellValue('C4','Kategori A');$gb2->setCellValue('C5','Kategori B');$gb2->setCellValue('C7','Kategori D');$gb2->setCellValue('F5',22);
$records=parseUnitSellOutTargets($book,2027);$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
checkUnitTarget($lookup['gb2|existing|1']==='34.000000','Shifted GB2 category columns not detected');checkUnitTarget($lookup['gb2|new|1']==='5.000000','Shifted GB2 D not detected');

// A later upload can restore missing categories; all of them must be counted again.
$gb2->setCellValue('C6','Kategori C');$gb2->setCellValue('F6',32);$gb2->setCellValue('F7',42);
$records=parseUnitSellOutTargets($book,2027);$lookup=[];foreach($records as $row)$lookup[$row['product'].'|'.$row['kind'].'|'.$row['month']]=$row['units'];
checkUnitTarget($lookup['gb2|existing|1']==='66.000000','Restored A/B/C must all count');
checkUnitTarget($lookup['gb2|new|1']==='42.000000','Restored D must count again');

$tmp=tempnam(sys_get_temp_dir(),'unit-target-');try{\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book,'Ods')->save($tmp);$loaded=readUnitSellOutTargetBook($tmp);checkUnitTarget($loaded->getSheetCount()===6,'Budget loaded');checkUnitTarget(count(parseUnitSellOutTargets($loaded,2027))===144,'Filtered ODS failed');$loaded->disconnectWorksheets();}finally{unlink($tmp);}
$book->disconnectWorksheets();
echo "PASS only TARGET/UNIT, A+B+C vs D, all six GB/months, category subtotal priority, no double count/Rupiah/Budget, formula/zero/blank, year validation and filtered ODS\n";
