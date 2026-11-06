<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/biaya_workbook.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function checkBiaya($ok,$message){if(!$ok)throw new RuntimeException($message);}
function rejectBiaya(callable $f,$message){try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $book=buildBiayaLegacyInputTemplate($year.'-09');
 $targetName=$year===2025?'TARGET ROLLING':'TARGET';
 checkBiaya($book->getSheetCount()===3,'Only two tables and hidden metadata');
 $target=$book->getSheetByName($targetName);$actual=$book->getSheetByName('REALISASI');
 foreach([$target,$actual] as $sheet)foreach(range(6,185) as $r){
  if($sheet===$actual){
   foreach(['B','C','D'] as $col) checkBiaya($sheet->getCell($col.$r)->getValue()==="='".$targetName."'!".$col.$r,'Actual identity references Target');
  } else {
  checkBiaya($sheet->getCell('D'.$r)->getValue()===null,'No source product names copied');
  checkBiaya($sheet->getCell('B'.$r)->getValue()===null,'Category starts empty');
  checkBiaya($sheet->getCell('C'.$r)->getValue()===null,'Product number starts empty');
  }
  foreach(range(5,16) as $c)checkBiaya($sheet->getCell([$c,$r])->getValue()===null,'All financial inputs blank');
 }
 rejectBiaya(fn()=>parseBiayaProducts($book,$year),'Entirely blank template must not replace saved data');
 foreach([[$target,6,'EXIS',1],[$target,26,'D',2],[$target,126,'EXIS',1]] as [$sheet,$r,$category,$number]) { $sheet->setCellValue('B'.$r,$category);$sheet->setCellValue('C'.$r,$number); }
 $target->setCellValue('D6','Synthetic Existing');$target->setCellValue('E6',100);$target->setCellValue('F6',0);
 $target->setCellValue('D26','Synthetic New');$target->setCellValue('E26',25);
 $target->setCellValue('D126','Synthetic GB5');$target->setCellValue('E126',3.25);
 $actual->setCellValue('E6',50.5);
 foreach(['Xlsx','Ods'] as $format){
  $path=tempnam(sys_get_temp_dir(),'biaya-template-');
  try{
   IOFactory::createWriter($book,$format)->save($path);$loaded=IOFactory::load($path);
   $rows=parseBiayaProducts($loaded,$year);checkBiaya(count($rows)===6,'Unused rows skipped');
   $summary=parseBiayaWorkbook($loaded,$year);
   $actualRows=array_values(array_filter($rows,fn($row)=>$row['type']==='actual'));
   checkBiaya($actualRows[0]['name']==='Synthetic Existing' && $actualRows[0]['category']==='EXIS' && $actualRows[0]['number']===1,'Linked identities read on upload');
   checkBiaya($summary['target']['existing1'][0]===100.0,'Existing target read');
   checkBiaya($summary['target']['existing1'][1]===0.0,'Explicit zero retained');
   checkBiaya($summary['target']['existing1'][2]===null,'Unknown amount retained');
   checkBiaya($summary['target']['new1'][0]===25.0,'New product category read');
   checkBiaya($summary['actual']['existing1'][0]===50.5,'Actual read with decimals preserved internally');
   $normalized=$summary['target']['existing5'][0]/($year===2026?1000:1);
   checkBiaya($normalized===3.25,'Consistent Rp (000) input and legacy GB5 output normalization');
   rejectBiaya(fn()=>parseBiayaProducts($loaded,$year+1),'Wrong year rejected');
   $loaded->disconnectWorksheets();
  }finally{unlink($path);}
 }
 $target->setCellValue('E7',5);rejectBiaya(fn()=>parseBiayaProducts($book,$year),'Amounts without product identity rejected');$target->setCellValue('E7',null);
 $target->setCellValue('B7','EXIS');$target->setCellValue('D7','Duplicate ID');$target->setCellValue('C7',1);rejectBiaya(fn()=>parseBiayaProducts($book,$year),'Duplicate product rejected');
 $target->setCellValue('D7',null);$target->setCellValue('C7',2);
 $target->setCellValue('E6','invalid');rejectBiaya(fn()=>parseBiayaProducts($book,$year),'Invalid text rejected');
 $book->disconnectWorksheets();
}
echo "PASS empty BIAYA templates 2025/2026/2027, rolling 2025, product rows, XLSX/ODS upload roundtrip, categories, sums, zero/blank, units, duplicate/year/invalid input validation\n";
