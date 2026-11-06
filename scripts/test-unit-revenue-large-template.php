<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/unit_revenue_input_template.php';
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
function checkLarge($ok,$message){if(!$ok)throw new RuntimeException($message);}
$book=buildUnitRevenueLegacyInputTemplate('2026-11');$u=$book->getSheetByName('UNIT');$r=$book->getSheetByName('REVENUE');
checkLarge(count($u->getDataValidationCollection())===3,'Range validation keeps large templates efficient');
checkLarge($u->getCell('A1005')->getDataValidation()->getType()===DataValidation::TYPE_LIST,'GB list reaches final row');
checkLarge($u->getCell('E1005')->getDataValidation()->getFormula1()==='"A,B,C,ABC,D,E,KAT E,NP"','Category list reaches final row');
checkLarge($r->getCell('Q1005')->getDataValidation()->getType()===DataValidation::TYPE_DECIMAL,'Numeric validation reaches final month/row');
foreach(['A'=>'GB 1','B'=>'0007','D'=>'Synthetic last row','E'=>'A'] as $col=>$value)$u->getCell($col.'1005')->setValueExplicit($value,DataType::TYPE_STRING);
$u->setCellValue('F1005',7);$r->setCellValue('F1005',70);
$path=tempnam(sys_get_temp_dir(),'large-template-');
try{
 IOFactory::createWriter($book,'Xlsx')->save($path);$loaded=IOFactory::load($path);
 checkLarge($loaded->getSheetByName('REVENUE')->getCell('D1005')->getCalculatedValue()==='Synthetic last row','Last-row identity formula survives export');
 checkLarge((float)$loaded->getSheetByName('REVENUE')->getCell('R1005')->getCalculatedValue()===70.0,'Last-row total survives export');
 checkLarge($loaded->getSheetByName('UNIT')->getCell('F1005')->getDataValidation()->getType()===DataValidation::TYPE_DECIMAL,'Validation range survives export');
 $records=parseUnitRevenueInputTemplate($loaded,'2026-11');
 checkLarge(count($records)===4&&$records[0]['product_code']==='0007'&&$records[0]['qty'][0]===7.0&&$records[0]['rev'][0]===70.0,'Large template uploads correctly');
 checkLarge(min(unitRevenueEthicalManualRows($loaded))>1005,'Ethical stays below all products');
}finally{unlink($path);}
echo "PASS 1000-row optimized template: validation, last-row links/totals, leading zeros, Ethical position and XLSX upload roundtrip\n";
