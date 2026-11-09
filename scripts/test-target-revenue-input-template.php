<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/target_revenue_input_template.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function checkRevenue($condition,$message) {if(!$condition)throw new RuntimeException($message);}
function rejectedRevenue(callable $fn) {try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Invalid input accepted');}
$book=buildReportInputTemplate('TARGET REVENUE','2026-01');
checkRevenue($book->getSheetCount()===7,'six groups and metadata');
rejectedRevenue(fn()=>parseTargetRevenueInputTemplate($book,'2026-09'));
$sheet=$book->getSheetByName('GB 1');
$sheet->setCellValue('B8','SYNTHETIC A')->setCellValue('C8',10)->setCellValue('D8',20)->setCellValue('N8',30);
$sheet->setCellValue('B9','SYNTHETIC B')->setCellValue('C9','=C8*2');
$parsed=parseTargetRevenueInputTemplate($book,'2026-09');
checkRevenue($parsed['rows'][0]['months'][0]===30.0,'detail formulas calculated');
checkRevenue($parsed['rows'][0]['months'][11]===30.0,'full year preserved');
checkRevenue($parsed['details'][1]['A'][0]['months'][2]===null,'blank distinct from zero');
checkRevenue(count($parsed['rows'])===30,'all category summaries present');
// Subtotals are derived from product detail, not stale or altered summaries.
$sheet->setCellValue('C58',999999);
checkRevenue(parseTargetRevenueInputTemplate($book,'2026-09')['rows'][0]['months'][0]===30.0,'ignore displayed totals');
// Move rows and columns, add products, and remove unused product rows.
$sheet->insertNewRowBefore(9,70);
$sheet->setCellValue('B70','SYNTHETIC ADDED')->setCellValue('C70',5);
$sheet->removeRow(80,20);
$sheet->insertNewColumnBefore('C',2);
$book->getCalculationEngine()->clearCalculationCache();
$parsed=parseTargetRevenueInputTemplate($book,'2026-09');
checkRevenue($parsed['rows'][0]['months'][0]===35.0,'flexible rows and monthly columns');
checkRevenue(count($parsed['details'][1]['A'])===3,'all added products read');
rejectedRevenue(fn()=>parseTargetRevenueInputTemplate($book,'2025-09'));
$history=$parsed['details'];
$next=buildTargetRevenueInputTemplate('2026-10',$history);
$nextParsed=parseTargetRevenueInputTemplate($next,'2026-10');
checkRevenue($nextParsed['rows']===$parsed['rows'],'annual targets carried forward');
checkRevenue($next->getSheetByName('GB 1')->getCell('B11')->getValue()==='KATEGORI A','exactly three saved products without spare blank rows');
checkRevenue($next->getSheetByName('GB 2')->getCell('B8')->getValue()==='KATEGORI A','unused category has no product rows');
checkRevenue($next->getSheetByName('GB 2')->getCell('C8')->getValue()==='=0','empty subtotal avoids inverted SUM range');
checkRevenue($next->getSheetByName('GB 2')->getCell('C8')->getCalculatedValue()===0,'empty subtotal calculates without circular references');
$fresh=buildReportInputTemplate('TARGET REVENUE','2026-10');
checkRevenue($fresh->getSheetByName('GB 1')->getCell('B8')->getValue()==='KATEGORI A','non-January fresh download has no unused product rows');
$fresh->disconnectWorksheets();
$reset=buildTargetRevenueInputTemplate('2026-01',$history);
checkRevenue($reset->getSheetByName('GB 1')->getCell('B8')->getValue()===null,'January discards history');
checkRevenue($reset->getSheetByName('GB 1')->getCell('B58')->getValue()==='KATEGORI A','January has exactly fifty product rows');
checkRevenue($reset->getSheetByName('GB 1')->getCell('C8')->getValue()===null,'January monthly input is blank');
$reset->disconnectWorksheets();
checkRevenue($next->getSheetByName('GB 1')->getCell('P8')->getValue()==='=SUM(C8:N8)','simple totals');
checkRevenue($next->getSheetByName('GB 1')->getStyle('C8')->getNumberFormat()->getFormatCode()==='#,##0;[Red](#,##0);0','no displayed decimals');
foreach(['Xlsx','Ods'] as $format) {
    $path=tempnam(sys_get_temp_dir(),'target-revenue-');
    try {
        $writer=IOFactory::createWriter($next,$format);$writer->setPreCalculateFormulas(false);$writer->save($path);
        $reader=IOFactory::createReader($format);$loaded=$reader->load($path);
        checkRevenue(parseTargetRevenueInputTemplate($loaded,'2026-10')['rows']===$nextParsed['rows'],$format.' round trip');
        $loaded->disconnectWorksheets();
    }finally{unlink($path);}
}
$next->getSheetByName('GB 1')->setCellValue('C8',-1);
rejectedRevenue(fn()=>parseTargetRevenueInputTemplate($next,'2026-10'));
$next->getSheetByName('GB 1')->setCellValue('C8','#VALUE!');
rejectedRevenue(fn()=>parseTargetRevenueInputTemplate($next,'2026-10'));
$next->getSheetByName('GB 1')->setCellValue('C8',0)->setCellValue('B8','');
rejectedRevenue(fn()=>parseTargetRevenueInputTemplate($next,'2026-10'));
$jan=buildReportInputTemplate('TARGET REVENUE','2027-01');
checkRevenue($jan->getSheetByName('GB 1')->getCell('B8')->getValue()===null,'January fresh');
rejectedRevenue(fn()=>parseTargetRevenueInputTemplate($jan,'2027-01'));
foreach([$book,$next,$jan] as $b)$b->disconnectWorksheets();
echo "PASS Target Revenue: summaries, formulas, flexible rows/columns, history, January, validation, XLSX/ODS.\n";
