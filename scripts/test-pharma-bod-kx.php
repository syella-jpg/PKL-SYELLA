<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/bod_kx_sell_in_input_store.php';
$GLOBALS['reportAccessUser']='pharma';
$state=['year'=>2026,'sales'=>[],'rod'=>[]];
foreach(['gb1','gb2','gb4'] as $group)$state['rod'][]=['product'=>$group,'code'=>'dummy-'.$group,'name'=>'Dummy '.$group,'price'=>10.0,'units'=>array_fill(0,12,1.0)];
$book=buildBodKxInputTemplate('2026-11',$state);$tables=locateBodKxInputTables($book->getSheetByName('ROD'),2026,true);
if(array_keys($tables)!==['gb1','gb4'])throw new RuntimeException('Other GB in template');
foreach($book->getAllSheets() as $sheet)foreach($sheet->getCellCollection()->getCoordinates() as $address)if($sheet->getCell($address)->getValue()==='Dummy gb2')throw new RuntimeException('Other GB name leaked');
$incoming=parseBodKxInputTemplate($book,'2026-11',true);foreach($incoming['rod'] as $row)if(!in_array($row['product'],['gb1','gb4'],true))throw new RuntimeException('Other GB parsed');
$incoming=['year'=>2026,'sales'=>[],'rod'=>[],'_rod_template'=>true,'_allowed_groups'=>['gb1','gb4']];
$merged=mergeBodKxInputState($state,$incoming);
if($merged['state']['rod']!==[$state['rod'][1]])throw new RuntimeException('Other GB detail modified during removal');
if(isset($merged['touches']['rod']['gb2']))throw new RuntimeException('Other GB totals modified');
$book->disconnectWorksheets();echo "PASS dummy Pharma BOD KX template/read and scoped ROD deletion preserve unrelated GB details/totals. No DB accessed.\n";
