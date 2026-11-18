<?php
require_once __DIR__.'/../includes/bod_bi_input_template.php';
function bodRowsCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$p=tempnam(sys_get_temp_dir(),'bod-row-change-');
try{
 file_put_contents($p,buildBodBiInputTemplateOds('BOD BI.Opr & BOD BI.SDM','2025-03'));
 foreach([5,-3] as $change){
  $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($p);$raw=$book->getSheetByName('FEBRUARI');
  // Change unused rows above recruitment while keeping the named input rows.
  if($change>0)$raw->insertNewRowBefore(12,$change);else $raw->removeRow(16,-$change);
  $find=static function($sheet,$label){foreach($sheet->getCellCollection()->getCoordinates() as $a)if(preg_replace('/[^A-Z0-9]/','',strtoupper((string)$sheet->getCell($a)->getValue()))===$label)return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($a)[1];throw new RuntimeException('Source label missing');};
  $subtotal=$find($raw,'SUBTOTALI');$recruitment=$find($raw,'RECRUITMENT');$salary=$find($raw,'GAJIUPAHDLL');
  $raw->setCellValue('G'.$salary,180);$raw->setCellValue('G'.$recruitment,50);$jan=$book->getSheetByName('JANUARI');$jan->setCellValue('G'.$find($jan,'GAJIUPAHDLL'),100);$jan->setCellValue('G'.$find($jan,'RECRUITMENT'),20);
  $opr=$book->getSheetByName('BOD BI.Opr');$sdm=$book->getSheetByName('BOD BI.SDM');
  bodRowsCheck(str_contains($opr->getCell('C4')->getValue(),'SUMIF('),'OPR uses label lookup');
  bodRowsCheck(str_contains($sdm->getCell('E8')->getValue(),'RECRUITMENT'),'SDM uses item lookup');
  bodRowsCheck((float)$opr->getCell('C4')->getCalculatedValue()===230.0,'Subtotal includes salary/recruitment after row changes');
  bodRowsCheck((float)$opr->getCell('B4')->getCalculatedValue()===110.0,'Monthly difference remains correct');
  bodRowsCheck((float)$sdm->getCell('E7')->getCalculatedValue()===80.0,'Salary remains correct');
  bodRowsCheck((float)$sdm->getCell('E8')->getCalculatedValue()===30.0,'Recruitment remains correct');
  $book->disconnectWorksheets();
 }
 echo "PASS: inserting and deleting monthly detail rows updates OPR/SDM references, subtotal SUM and live cumulative/monthly calculations.\n";
}finally{@unlink($p);}
