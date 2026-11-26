<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_template_history.php';
require __DIR__.'/../includes/biaya_workbook.php';
require __DIR__.'/../includes/unit_revenue_template_history.php';
require __DIR__.'/../includes/target_revenue_input_template.php';
require __DIR__.'/../includes/unit_sell_out_targets.php';
function verifyScope($ok,$message){if(!$ok)throw new RuntimeException($message);}
$GLOBALS['reportAccessUser']='pharma';
$records=[];foreach([1,2,4,6] as $gb)$records[]=['report_year'=>2026,'report_period'=>'2026-10','jenis'=>'target','gb'=>$gb,'kategori'=>'EXIS','nomor_produk'=>1,'nama_produk'=>'Dummy GB '.$gb,'jan'=>10];
$b=buildBiayaTemplateWithHistory('2026-11',$records);$rows=parseBiayaProducts($b,2026);verifyScope(array_values(array_unique(array_column($rows,'gb')))===[1,4],'Cost history/template scoped');$b->disconnectWorksheets();
$records=[];foreach([1,2,4,6] as $gb)$records[]=['gb'=>(string)$gb,'product_code'=>'dummy'.$gb,'principal_product_code'=>'','product_name'=>'Dummy GB '.$gb,'kategori'=>'A','qty'=>array_fill(0,12,1.0),'rev'=>array_fill(0,12,10.0)];
$b=buildUnitRevenueTemplateWithHistory('2026-11',['source_period'=>'2026-10','records'=>$records]);$rows=parseUnitRevenueInputTemplate($b,'2026-11');verifyScope(array_values(array_unique(array_column($rows,'gb')))===['1','4'],'Combined template history scoped');verifyScope(!str_contains(json_encode($rows),'Dummy GB 2'),'No other GB product');$b->disconnectWorksheets();
$b=buildTargetRevenueInputTemplate('2026-11');verifyScope($b->getSheetNames()===['GB 1','GB 4','_TEMPLATE'],'Revenue target template sheets');$b->disconnectWorksheets();
$b=buildUnitSellOutProductTemplate('2026-11',1,2,true,true,array_fill_keys(['gb1','gb2','gb3','gb4','gb5','gb6'],array_fill_keys(['A','B','C','D'],1)));
foreach($b->getAllSheets() as $sheet)if(preg_match('/^GB [2356]$/D',$sheet->getTitle()))$b->removeSheetByIndex($b->getIndex($sheet));
foreach([1,4] as $gb){$sheet=$b->getSheetByName('GB '.$gb);$layout=locateUnitSellOutCategoryTables($sheet)['A'];$sheet->setCellValue([$layout['columns']['name'],$layout['first']],'Dummy unit '.$gb);$sheet->setCellValue([$layout['columns']['months'][0],$layout['first']],10);}
$data=parseUnitSellOutInputTemplate($b,2026);foreach($data['records'] as $row)if(!in_array(reportGb($row['product']),[1,4],true))verifyScope($row['units']===null,'Hidden other groups remain empty');$b->disconnectWorksheets();
unset($GLOBALS['reportAccessUser']);$b=buildTargetRevenueInputTemplate('2026-11');verifyScope($b->getSheetCount()===7,'Existing accounts retain six GBs');$b->disconnectWorksheets();
echo "PASS dummy scoped Pharma templates: BIAYA, UNIT/Revenue history, only GB1/4 identities, target sheets, existing-account behaviour retained. No live DB accessed.\n";
