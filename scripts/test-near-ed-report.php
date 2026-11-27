<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/near_ed_input_store.php';
function checkNearReport($condition,$message){if(!$condition)throw new RuntimeException($message);}
$b=buildNearEdInputTemplate('2026-11',14);
foreach(nearEdGroups() as $group){$s=$b->getSheetByName($group);for($i=0;$i<12;$i++){
    $row=6+$i;$s->setCellValue('B'.$row,'Dummy '.$group.' '.$i);$s->setCellValue('F'.$row,100);$s->setCellValue('I'.$row,1000);
    foreach(nearEdBuckets() as $bucket=>[$label,$units,$rp]){$s->setCellValue($units.$row,$i);$s->setCellValue($rp.$row,$bucket===1?($i<3?10:0):$i*10);}
}}
$path=tempnam(sys_get_temp_dir(),'dummy-near-report-');
try{
 $w=new PhpOffice\PhpSpreadsheet\Writer\Xlsx($b);$w->setPreCalculateFormulas(false);$w->save($path);
 $groups=readNearEdInput(file_get_contents($path));$report=buildNearEdReport('2026-11',$groups);
 checkNearReport($report->getSheetNames()===['NEAR ED','TOP 10 NEAR ED'],'Only two report sheets');
 checkNearReport($report->getSheetByName('NEAR ED')->getCell('C5')->getValue()===66.0,'Sum units from input');
 checkNearReport($report->getSheetByName('NEAR ED')->getCell('E6')->getValue()===30.0,'Sum second bucket');
 $top=$report->getSheetByName('TOP 10 NEAR ED');
 checkNearReport($top->getCell('B6')->getValue()==='Dummy GB 1 11','Descending ranking');
 checkNearReport($top->getCell('C6')->getValue()==='Dummy GB 1 0','Stable ties');
 checkNearReport($top->getCell('C9')->getValue()==='','Less than ten positive values');
 foreach($report->getAllSheets() as $s)foreach($s->getCoordinates() as $coordinate)checkNearReport($s->getCell($coordinate)->getDataType()!=='f','No dangling formulas');
 $w=new PhpOffice\PhpSpreadsheet\Writer\Xlsx($report);$w->save($path);$roundtrip=PhpOffice\PhpSpreadsheet\IOFactory::load($path);
 checkNearReport($roundtrip->getSheetByName('TOP 10 NEAR ED')->getCell('B6')->getValue()==='Dummy GB 1 11','Export roundtrip');
 checkNearReport(buildNearEdReport('2026-10',[])->getSheetByName('TOP 10 NEAR ED')->getCell('B6')->getValue()==='','No upload means empty report');
 $summary=$report->getSheetByName('NEAR ED');
 checkNearReport($summary->getStyle('A5')->getBorders()->getBottom()->getBorderStyle()==='thin','Summary table borders');
 foreach(['D5','F5','D9','F9'] as $cell)checkNearReport($summary->getStyle($cell)->getNumberFormat()->getFormatCode()==='0.00%','Summary percentages use two decimals');
 checkNearReport($summary->getStyle('B5')->getFill()->getStartColor()->getARGB()!==$summary->getStyle('B6')->getFill()->getStartColor()->getARGB(),'Alternating summary rows');
 checkNearReport($summary->getStyle('A47')->getFont()->getColor()->getARGB()==='FFFFFFFF','Prominent grand total');
 (new PhpOffice\PhpSpreadsheet\Writer\Ods($report))->save($path);
 $zip=new ZipArchive();checkNearReport($zip->open($path)===true,'ODS opens');
 $xml=$zip->getFromName('content.xml').$zip->getFromName('styles.xml');$zip->close();
 checkNearReport(stripos($xml,'#405b6a')!==false,'Muted table colors exported');
 checkNearReport(strpos($xml,'fo:border')!==false,'Table borders exported');
 echo "PASS synthetic NEAR ED report: all groups, totals, ranking, ties, blank slots, export roundtrip, no input-sheet references.\n";
}finally{@unlink($path);}
