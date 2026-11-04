<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/report_input_templates.php';
require __DIR__.'/../includes/mtm_template_history.php';
require __DIR__.'/../includes/mtm_workbook.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function verify($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$records=[];
foreach (['EXISTING','NEW_PRODUCT'] as $i=>$metric) foreach (['TARGET','ROLLING','ACTUAL'] as $j=>$type) {
    for ($month=1;$month<=12;$month++) $records[]=[
        'report_period'=>'2026-11','metric_code'=>$metric,'value_type'=>$type,'month_number'=>$month,
        'amount'=>($type==='ACTUAL'&&$month===12)?null:($month===5?0:($i+1)*100+$j*10+$month)
    ];
}
$records[]=['report_period'=>'2025-12','metric_code'=>'EXISTING','value_type'=>'TARGET','month_number'=>1,'amount'=>999999];
$records[]=['report_period'=>'2026-12','metric_code'=>'APPENDIX','value_type'=>'FILE','month_number'=>0,'amount'=>null];
$book=buildReportInputTemplate('MTM','2026-12');
verify(fillMtmTemplateHistory($book,'2026-12',$records)==='2026-11','December must use November snapshot, ignoring appendix/other years');
$sheet=$book->getSheetByName('MTM');
verify($sheet->getCell('C10')->getValue()===101.0,'Initial target carried');
verify($sheet->getCell('C11')->getValue()===111.0,'Rolling target carried separately');
verify($sheet->getCell('G12')->getValue()===0.0,'Explicit zero carried');
verify($sheet->getCell('N12')->getValue()===null,'New actual month stays blank');
verify($sheet->getCell('N10')->getValue()===112.0,'Previously entered future target retained');
verify($sheet->getCell('C6')->getValue()==='=C10+C14','Revenue formula retained');
verify($sheet->getCell('C6')->getCalculatedValue()===302.0,'Revenue formula recalculates carried inputs');
verify(str_contains($sheet->getCell('A2')->getValue(),'DESEMBER 2026'),'New period title retained');
validateReportInputTemplate($book,'MTM','2026-12');
$path=tempnam(sys_get_temp_dir(),'mtm-carry-');
try {
    IOFactory::createWriter($book,'Xlsx')->save($path);
    $loaded=IOFactory::load($path);
    $data=parseMtmWorkbook($loaded);
    verify($data['EXISTING']['TARGET'][1]===101.0,'Downloaded template can be uploaded again');
    verify($data['NEW_PRODUCT']['ROLLING'][1]===211.0,'New rolling carried');
} finally {unlink($path);}
foreach (['2027-01','2027-02'] as $period) {
    $empty=buildReportInputTemplate('MTM',$period);
    verify(fillMtmTemplateHistory($empty,$period,$records)===null,'January reset and new year isolation');
    verify($empty->getSheetByName('MTM')->getCell('C10')->getValue()===null,'No previous-year values');
}
$past=buildReportInputTemplate('MTM','2026-10');
verify(fillMtmTemplateHistory($past,'2026-10',$records)==='2026-11','Later upload supplies earlier months if no historical snapshot exists');
verify($past->getSheetByName('MTM')->getCell('L10')->getValue()===110.0,'October target carried from later upload');
verify($past->getSheetByName('MTM')->getCell('M10')->getValue()===111.0,'November initial target remains visible in October');
verify($past->getSheetByName('MTM')->getCell('M11')->getValue()===null,'November rolling stays excluded from October');
verify($past->getSheetByName('MTM')->getCell('M12')->getValue()===null,'November actual stays excluded from October');
verify($past->getSheetByName('MTM')->getCell('N10')->getValue()===112.0,'December initial target remains visible in October');
$jan=buildReportInputTemplate('MTM','2026-01');
fillMtmTemplateHistory($jan,'2026-01',$records);
verify($jan->getSheetByName('MTM')->getCell('N10')->getValue()===112.0,'January download can display stored initial targets for entire same year');
verify($jan->getSheetByName('MTM')->getCell('C11')->getValue()===null,'January rolling resets');
verify($jan->getSheetByName('MTM')->getCell('C12')->getValue()===null,'January actual resets');
// Latest snapshot wins without resurrecting a deliberately cleared value.
$records[]=['report_period'=>'2026-12','metric_code'=>'EXISTING','value_type'=>'TARGET','month_number'=>1,'amount'=>null];
$latest=buildReportInputTemplate('MTM','2026-12');
verify(fillMtmTemplateHistory($latest,'2026-12',$records)==='2026-12','Same-period re-download uses newest saved snapshot');
verify($latest->getSheetByName('MTM')->getCell('C10')->getValue()===null,'Cleared latest value does not pull older value');
echo "PASS November-to-December carry, all three value types, zero/blank, formulas, upload roundtrip, January reset, year isolation, future-period exclusion, latest snapshot\n";
