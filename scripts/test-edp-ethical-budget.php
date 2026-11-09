<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/bod_mnj_workbook.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
function budgetCheck($condition,$message){if(!$condition)throw new RuntimeException($message);}
foreach([[2026,9],[2027,1],[2028,12]] as [$year,$month]){
 $book=new Spreadsheet();$source=$book->getActiveSheet();$source->setTitle('BOD MNJ');$target=$book->createSheet();$target->setTitle('REVENUE EDP MNJ');$sr=1;$tr=1;$refs=[];
 foreach([$year,$year-1] as $y){
  $source->setCellValue('B'.$sr++,'BIAYA PROMOSI MNJ  TH '.$y);$source->setCellValue('B'.$sr,'GB 1')->setCellValue('D'.$sr++,99999);
  $from=$sr++;$source->setCellValue('B'.$from,'ETHICAL');$values=[];
  foreach(range(0,11) as $m){$v=$m===1?0.0:($m===2?null:(float)(($m+1)*($y-$year+2)));$values[]=$v;if($v!==null)$source->setCellValue([$m+4,$from],$v);}
  $target->setCellValue('A'.$tr++,'TARGET BIAYA '.$y);$target->setCellValue('B'.$tr,'EXIST GB 1')->setCellValue('D'.$tr++,777);$to=$tr++;
  $target->setCellValue('B'.$to,'TOTAL ETHICAL');foreach(range(3,16) as $col)$target->setCellValue([$col,$to],888);
  $target->setCellValue('A'.$tr++,'BIAYA TAHUN '.$y);$target->setCellValue('B'.$tr,'TOTAL ETHICAL')->setCellValue('D'.$tr++,999);$refs[]=[$from,$to,$values];
 }
 $before=[];foreach($target->getCellCollection()->getCoordinates() as $addr)$before[$addr]=$target->getCell($addr)->getValue();$sourceBefore=$source->toArray();
 fillEdpEthicalBudget($book,sprintf('%d-%02d',$year,$month));$allowed=[];
 foreach($refs as [$from,$to,$values]){
  foreach(range(3,16) as $col)$allowed[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$to]=true;
  foreach($values as $m=>$v){$cell=$target->getCell([$m+4,$to]);if($v===null)budgetCheck($cell->getValue()===null,'unknown monthly value stays blank');else{budgetCheck($cell->getOldCalculatedValue()===$v,'correct year/month value');$ref=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($m+4).$from;budgetCheck($cell->getValue()==="='BOD MNJ'!".$ref,'direct monthly link');}}
  budgetCheck($target->getCell('C'.$to)->getOldCalculatedValue()==array_sum(array_slice($values,0,$month)),'period-aware YTD');
  budgetCheck($target->getCell('P'.$to)->getOldCalculatedValue()==array_sum($values),'full year sum');
 }
 foreach($before as $addr=>$v)if(!isset($allowed[$addr]))budgetCheck($target->getCell($addr)->getValue()===$v,'unrelated actual/GB cell changed');
 budgetCheck($source->toArray()===$sourceBefore,'source changed');$book->disconnectWorksheets();
}
echo "PASS PHP Ethical TARGET BIAYA: monthly/year links, YTD/FY, zero/blank, source and other rows preserved.\n";
