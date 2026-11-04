<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/mtm_workbook.php';
require __DIR__.'/../dashboard/revenue_ibfm_processor.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function fails(callable $fn, string $message) { try { $fn(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException($message); }
foreach ([2025,2026,2027] as $year) foreach (['IB','MTM'] as $section) {
    $period = $year.'-09';
    $book = buildReportInputTemplate($section,$period);
    validateReportInputTemplate($book,$section,$period);
    check($book->getSheetByName('_TEMPLATE')->getSheetState()==='hidden','Metadata hidden');
    fails(fn()=>validateReportInputTemplate($book,$section,($year+1).'-09'),'Wrong year must fail');
    fails(fn()=>validateReportInputTemplate($book,$section==='IB'?'MTM':'IB',$period),'Wrong section must fail');
    $sheets = $section === 'IB' ? ['YFR - IBFM','IAP','HRU'] : ['MTM'];
    foreach ($sheets as $name) {
        $sheet=$book->getSheetByName($name);
        $first=$section==='IB'?5:3;
        if ($section==='IB') {
            check($sheet->getCell('B5')->getValue()==='OBJECTIVE','IB Objective column');
            check($sheet->getCell('C5')->getValue()==='KPI','IB KPI column');
            if ($name==='HRU') check($sheet->getCell('B6')->getValue()==='COST EFFICIENCY & EFFECTIVENESS','HRU cost objective');
        }
        $rows=$section==='MTM'?[6,7,8,10,11,12,14,15,16]:($name==='HRU'?[6,7]:[6,7,9,10]);
        foreach (array_filter($rows,fn($r)=>$section!=='MTM'||$r>8) as $row) for ($m=0;$m<12;$m++) check($sheet->getCell([$first+$m,$row])->getValue()===null,'Input cells must be truly empty');
        $sheet->setCellValue([$first,$section==='MTM'?10:6],'not a number');
        fails(fn()=>validateReportInputTemplate($book,$section,$period),'Invalid pasted text must fail');
        foreach ($rows as $row) for ($m=0;$m<12;$m++) {
            if ($section==='MTM' && $row<=8) continue;
            // Synthetic only. IB is cumulative; MTM is a monthly amount.
            $value=$section==='IB'?($m+1)*($row+1):($m+1)*10+$row;
            $sheet->setCellValue([$first+$m,$row],$value);
        }
        foreach ($rows as $row) if ($section==='MTM' ? in_array($row,[12,16],true) : ($row-7)%3===0) {
            $sheet->setCellValue([$first+2,$row],0);
            $sheet->setCellValue([$first+3,$row],null);
        }
    }
    validateReportInputTemplate($book,$section,$period);
    foreach (['Xlsx','Ods'] as $format) {
        $path=tempnam(sys_get_temp_dir(),'input-template-');
        try {
            IOFactory::createWriter($book,$format)->save($path);
            $loaded=IOFactory::load($path);
            validateReportInputTemplate($loaded,$section,$period);
            if ($section==='MTM') {
                $data=parseMtmWorkbook($loaded);
                check(count($data)===3,'All MTM metrics read');
                check($data['REVENUE']['TARGET'][1]===44.0,'Revenue initial sums Existing + New Product');
                check($data['REVENUE']['TARGET'][2]===64.0,'Revenue monthly February sum');
                check($data['REVENUE']['ROLLING'][1]===46.0,'Revenue rolling sums matching rolling rows');
                check($data['EXISTING']['TARGET'][1]===20.0,'Existing initial target');
                check($data['EXISTING']['ROLLING'][1]===21.0,'Existing rolling target');
                check($data['NEW_PRODUCT']['ROLLING'][1]===25.0,'New product rolling target');
                check($data['REVENUE']['ACTUAL'][3]===0.0,'MTM zero retained');
                check($data['REVENUE']['ACTUAL'][4]===0.0,'Direct addition of empty source cells evaluates to zero');
                check($loaded->getSheetByName('MTM')->getCell('C6')->getValue()==='=C10+C14','Simple initial sum formula');
                check($loaded->getSheetByName('MTM')->getCell('C7')->getValue()==='=C11+C15','Simple rolling sum formula');
                check($loaded->getSheetByName('MTM')->getCell('C8')->getValue()==='=C12+C16','Simple actual sum formula');
            } else {
                foreach (['YFR - IBFM','IAP'] as $name) {
                    foreach (['SALES REVENUE (USD)','SALES REVENUE NEW BUSINESS PRODUCT (USD)'] as $metric) {
                        $data=processRevenueExistIbFmSpreadsheet($path,$year,$name,true,$metric);
                        $step=$metric==='SALES REVENUE (USD)'?7.0:10.0;
                        check($data['target'][1]===$step,'IB January cumulative baseline');
                        check($data['target'][2]===$step,'IB February cumulative difference');
                        check($data['actual'][4]===null,'IB missing month remains unknown');
                        check($data['actual'][5]===null,'IB no fabricated difference after gap');
                    }
                }
                $data=processRevenueExistIbFmSpreadsheet($path,$year,'HRU',true,'MCM = REVENUE - HPP – EXPENSES',15);
                check($data['target'][2]===7.0,'MCM cumulative difference');
            }
            $loaded->disconnectWorksheets();
        } finally { unlink($path); }
    }
    $book->disconnectWorksheets();
}
fails(fn()=>reportInputTemplateSpec('OTHER','2026-09'),'Unsupported section');
fails(fn()=>reportInputTemplateSpec('MTM','2026-13'),'Invalid period');
$blank=buildReportInputTemplate('MTM','2026-09');
fails(fn()=>parseMtmWorkbook($blank),'Empty MTM must not replace saved data');
echo "PASS empty styled templates, all IB/MTM metrics, XLSX/ODS round trips, cumulative IB/monthly MTM, years, zero/blank and invalid input validation\n";

$uploadSource = file_get_contents(__DIR__.'/../upload/upload.php');
$assigned = strpos($uploadSource, '$file = $_FILES');
$read = strpos($uploadSource, '$inputBook = IOFactory::load($file)');
check($assigned !== false && $read !== false && $read > $assigned, 'IB reader must run after uploaded file validation and path assignment');
echo "PASS IB upload validates and assigns the uploaded file before reading its template\n";
