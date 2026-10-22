<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_workbook.php';
$b = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$s = $b->getActiveSheet();
foreach (['A1'=>'REVENUE TAHUN 2026','B3'=>'NP GB 2','C3'=>100,'D3'=>0,'E3'=>50,'B4'=>'EXIST GB 1','C4'=>300,'A6'=>'BIAYA TAHUN 2026','B8'=>'EXIST GB 1','C8'=>80,'B9'=>'NP GB 2','C9'=>150,'D9'=>0,'A11'=>'MCM TAHUN 2026','B13'=>'EXIST GB 1','B14'=>'NP GB 2','B15'=>'TOTAL GB 3','C15'=>'=1+2','A17'=>'TARGET SELL OUT 2025','C19'=>999,'A21'=>'REVENUE TAHUN 2025','B23'=>'EXIST GB 1','C23'=>40,'A25'=>'BIAYA TAHUN 2025','B27'=>'EXIST GB 1','C27'=>10,'A29'=>'MCM TAHUN 2025','B31'=>'EXIST GB 1'] as $a=>$v) $s->setCellValue($a,$v);
populateMcmSheet($s,2026);
foreach (['C13'=>220,'C14'=>-50,'D14'=>0,'E14'=>null,'C15'=>null,'C31'=>30,'C19'=>999] as $a=>$expected) {
    $cell=$s->getCell($a); $v=$cell->isFormula()?$cell->getOldCalculatedValue():$cell->getValue();
    if ($v!==$expected) throw new RuntimeException($a.' expected '.json_encode($expected).' got '.json_encode($v));
}
if (!str_contains($s->getCell('C13')->getValue(),'C4-C8')) throw new RuntimeException('GB rows mismatched');
echo "PASS: MCM revenue minus biaya, reordered GB/NP rows, negative, zero, missing data, prior year and target preservation.\n";
scaleEdpRevenueToThousands($s);
foreach (['C3'=>0.1,'D3'=>0,'C4'=>0.3,'C23'=>0.04,'C8'=>80,'C19'=>999] as $address=>$expected) {
    if ($s->getCell($address)->getValue() != $expected) throw new RuntimeException('Revenue scaling: '.$address);
}
if ($s->getCell('F3')->getValue()!==null) throw new RuntimeException('Blank revenue was filled');
populateMcmSheet($s,2026);
if (abs($s->getCell('C13')->getOldCalculatedValue() - (0.3-80)) > 0.0000001) throw new RuntimeException('MCM must use scaled revenue');
echo "PASS: revenue /1000, decimal precision, zero/blank, prior year, biaya/target unchanged and scaled MCM.\n";
