<?php
// Synthetic fixture generator for the JavaScript tests of actual report readers.
// Run: php scripts/test-unit-revenue-report-totals.php | node scripts/test-unit-revenue-report-totals.cjs
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/includes/unit_revenue_input_template.php';
$fixtures=[];
foreach(['1','2','3','4','5','6','ET'] as $g=>$group)foreach(['A','B','C','ABC','D'] as $index=>$category){
    $factor=1+$g*5+$index;
    $fixtures[]=[
        'gb'=>$group,
        'product_code'=>str_pad((string)(count($fixtures)+1),5,'0',STR_PAD_LEFT),
        'principal_product_code'=>'00000'.($index+1),
        'product_name'=>'Synthetic product '.count($fixtures),
        'kategori'=>$category,
        'qty'=>array_map(static fn($m)=>$factor*$m,range(1,12)),
        'rev'=>array_map(static fn($m)=>$factor*$m*10,range(1,12)),
    ];
}
$book=normalizeUnitRevenueInputTemplate($fixtures);
$sheet=$book->getActiveSheet();
echo json_encode(['fixtures'=>$fixtures,'normalized'=>$sheet->rangeToArray('A5:AE'.$sheet->getHighestDataRow(),null,true,false,false)],JSON_THROW_ON_ERROR);
