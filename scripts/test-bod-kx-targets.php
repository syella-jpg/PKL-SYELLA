<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/bod_kx_sell_in_targets.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->setTitle('2026');
$months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AUG','SEPT','OKT','NOV','DES'];
foreach($months as $m=>$label){$c=11+$m*5;$s->setCellValue([$c,1],$label);$s->setCellValue([$c+3,2],'ROD Unit Terkecil Dari Unit Pesan');$s->setCellValue([$c+4,2],'Nilai ROD Unit Terkecil Dari Unit Pesan');}
foreach([3=>['GB 1','P1'],4=>['GB 1','P2'],5=>['GB ET','P3'],6=>['GB 2','P4'],7=>['GB 1','TOTAL']] as $r=>$record){$s->setCellValue('A'.$r,2026);$s->setCellValue('B'.$r,$record[0]);$s->setCellValue('C'.$r,$record[1]);}
foreach(['N3'=>900,'O3'=>10,'N4'=>800,'O4'=>20,'T3'=>2,'T4'=>3,'O5'=>0,'O7'=>9999,'H3'=>2] as $a=>$v)$s->setCellValue($a,$v);
$s->setCellValue('Y3','=T3*$H3');$s->setCellValue('BS1','TOTAL THN 2026');$s->setCellValue('BV2','Nilai ROD Unit Terkecil Dari Unit Pesan');$s->setCellValue('BV3',99999);
check(isBodKxRodSheet($s),'ROD detection failed');
$rows=parseBodKxRodTargets($s,2026);$lookup=[];foreach($rows as $row)$lookup[$row['product'].'-'.$row['month']]=$row['rupiah'];
check(count($rows)===36,'Wrong count');check((float)$lookup['gb1-1']===30.0,'GB sum/value column/detail filtering failed');
check((float)$lookup['gb1-2']===5.0,'Month mapping failed');check((float)$lookup['gb1-3']===4.0,'Formula calculation failed');
check($lookup['gb2-1']===null,'Blank target changed');check($lookup['ethical-1']==='0.000000','Ethical zero lost');
try{parseBodKxRodTargets($s,2025);throw new RuntimeException('Wrong year accepted');}catch(InvalidArgumentException $expected){}
$s->setCellValue('BR2','ROD Unit Terkecil Dari Unit Pesan');
try{parseBodKxRodTargets($s,2026);throw new RuntimeException('Missing value column accepted');}catch(InvalidArgumentException $expected){}
echo "PASS target-only values, monthly GB aggregation, GB ET, formula calculation, blank/zero, annual-total exclusion and year validation\n";
