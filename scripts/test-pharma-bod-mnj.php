<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/bod_mnj_pharma_scope.php';
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();
$s->fromArray([
 ['','SALES MNJ TH 2026'],['','GB 1',999],
 ['','BIAYA PROMOSI MNJ TH 2026'],['','RP.000- PPN','S/D OKTOBER','TARGET','TOTAL REALISASI'],
 ['','GB 1',10,20,30],['','GB 2 SECRET',999,999,999],['','GB 4',40,50,60],['','GRAND TOTAL',999],
 ['','BIAYA NON PRODUCT'],['','GB 1',999]
]);
$rows=bodMnjPharmaRows($s);if(count($rows)!==4)throw new RuntimeException('Wrong scoped rows');
if($rows[2][2]!==10||$rows[3][2]!==40)throw new RuntimeException('Wrong scoped values');
if(strpos(json_encode($rows),'999')!==false||strpos(json_encode($rows),'SECRET')!==false)throw new RuntimeException('Foreign values leaked');
$b->disconnectWorksheets();echo "PASS synthetic BOD MNJ scope retains cost headers/GB1/4 values, excludes sales, other GBs, corporate totals and non-product.\n";
