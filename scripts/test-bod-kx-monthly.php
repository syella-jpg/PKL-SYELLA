<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/bod_kx_sell_in_monthly.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();
$s->setCellValue('M6','Bulan Desember 2025');$s->setCellValue('P6','Bulan Januari 2025 s.d Desember 2025');
foreach(['M7'=>'Jumlah Unit','N7'=>'Rupiah','O7'=>'%','P7'=>'Jumlah Unit','Q7'=>'Rupiah'] as $a=>$v)$s->setCellValue($a,$v);
// Synthetic test numbers only, intentionally different between detail, monthly, cumulative and grand total.
foreach(['B8'=>'Item detail','M8'=>999,'N8'=>999,'B9'=>'Sub Total','D9'=>'01 - GB1','M9'=>12,'N9'=>34,'P9'=>888,'Q9'=>777,'B10'=>'Sub Total','D10'=>'02 - GB2','M10'=>0,'N10'=>0,'B11'=>'Sub Total','D11'=>'10 - ETHICAL','B12'=>'Sub Total','M12'=>666,'N12'=>666] as $a=>$v)$s->setCellValue($a,$v);
$rows=parseBodKxSellInMonthly($s,'2025-12');
check(count($rows)===3,'Detail or grand total was imported');
check($rows[0]['units']==='12'&&$rows[0]['rupiah']==='34','Monthly columns were not selected');
check($rows[1]['units']==='0'&&$rows[1]['rupiah']==='0','Zero was lost');
check($rows[2]['units']===null&&$rows[2]['rupiah']===null,'Blanks must remain null');
try{parseBodKxSellInMonthly($s,'2026-12');throw new RuntimeException('Year mismatch accepted');}catch(InvalidArgumentException $expected){}
try{parseBodKxSellInMonthly($s,'2025-11');throw new RuntimeException('Month mismatch accepted');}catch(InvalidArgumentException $expected){}
$s->setCellValue('M6','Bulan Januari 2025 s.d Desember 2025');
try{parseBodKxSellInMonthly($s,'2025-12');throw new RuntimeException('Cumulative header accepted');}catch(InvalidArgumentException $expected){}
echo "PASS monthly-only Sub Total, exclude cumulative/detail/grand total, blanks, zeros and period mismatch\n";
