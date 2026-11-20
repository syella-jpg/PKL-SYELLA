<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/unit_revenue_input_template.php';
$b=buildUnitRevenueInputTemplate('2026-10',7);$u=$b->getSheetByName('UNIT');$v=$b->getSheetByName('REVENUE');
$ul=locateUnitRevenueGroupTables($u);$vl=locateUnitRevenueGroupTables($v);$r=$ul[1]['first'];$s=$vl[1]['first'];
foreach(['B'=>'001','D'=>'Updated name','E'=>'A','F'=>12] as $c=>$value)$u->setCellValue($c.$r,$value);
foreach(['A'=>'1','B'=>'001','D'=>'Previous name','E'=>'A','F'=>120] as $c=>$value)$v->setCellValue($c.$s,$value);
$r2=$vl[2]['first'];
foreach(['A'=>'2','B'=>'002','D'=>'Revenue only','E'=>'D','F'=>45] as $c=>$value)$v->setCellValue($c.$r2,$value);
// Calc saves unquoted linked blank cells as numeric zero; these are not products.
$blank=$vl[3]['first'];$unitBlank=$ul[3]['first'];
foreach(range('A','E') as $c)$v->setCellValue($c.$blank,'=UNIT!'.$c.$unitBlank);
$v->setCellValue('F'.$blank,'=UNIT!F'.$unitBlank);
$records=parseUnitRevenueInputTemplate($b,'2026-10');$products=array_values(array_filter($records,fn($r)=>!isset($r['__kind'])));
if(count($products)!==2||$products[0]['qty'][0]!==12.0||$products[0]['rev'][0]!==120.0||$products[1]['rev'][0]!==45.0||$products[1]['qty'][0]!==null)throw new RuntimeException('Flexible merge lost values');
$b->disconnectWorksheets();
echo "PASS: changed names match by product code; revenue-only products retained with blank units.\n";
