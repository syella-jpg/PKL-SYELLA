<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/raw_native_period.php';
function checkInstructions($ok,$message){if(!$ok)throw new RuntimeException($message);}
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$b->getActiveSheet()->setTitle('DATA')->setCellValue('A1',123);
addRawTemplateInstructions($b);addRawTemplateInstructions($b);
checkInstructions($b->getSheetCount()===2&&$b->getSheet(0)->getTitle()==='PETUNJUK','First sheet, no duplicates');
checkInstructions($b->getSheetByName('DATA')->getCell('A1')->getValue()===123,'Data positions preserved');
checkInstructions($b->getSheet(0)->getStyle('A5')->getFont()->getColor()->getARGB()==='FFC00000','Red instructions');
checkInstructions(str_contains($b->getSheet(0)->getCell('A5')->getValue(),'pihak CSPPM'),'CSPPM wording');
$p=tempnam('/tmp','dummy-instructions-');
try{
 $w=new \PhpOffice\PhpSpreadsheet\Writer\Ods($b);$w->setPreCalculateFormulas(false);$w->save($p);
 $bytes=protectRawNativePeriod(file_get_contents($p),'2026-06');$bytes=protectRawNativePeriod($bytes,'2026-06');
 $tables=upbBjTables(rawPeriodDocument($bytes));checkInstructions(array_key_first($tables)==='PETUNJUK'&&count($tables)===2,'ODS instructions first, idempotent');
 file_put_contents($p,$bytes);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($p);
 checkInstructions($loaded->getSheetByName('PETUNJUK')->getStyle('A5')->getFont()->getColor()->getRGB()==='C00000','ODS red after reopen');
 checkInstructions($loaded->getSheetByName('DATA')->getCell('A1')->getValue()==123,'ODS source preserved');$loaded->disconnectWorksheets();
}finally{unlink($p);$b->disconnectWorksheets();}
echo "PASS synthetic template instructions, red styling, CSPPM wording, repeated export and unchanged data coordinates.\n";
