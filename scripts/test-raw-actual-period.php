<?php
require __DIR__.'/../includes/raw_actual_period.php';
function verifyPeriod($ok,$message){if(!$ok)throw new RuntimeException($message);}
$before=['gb1:1'=>[1,2,3,4,5,null,7,null,null,null,null,null]];
$after=$before;$after['gb1:1'][5]=60;assertRawActualPeriod('2026-06',$before,$after);
foreach([4,6] as $changed){$bad=$after;$bad['gb1:1'][$changed]=999;try{assertRawActualPeriod('2026-06',$before,$bad);throw new RuntimeException('Accepted foreign month');}catch(InvalidArgumentException $e){verifyPeriod(str_contains($e->getMessage(),'admin 2'),'Actionable message');}}
try{assertRawActualPeriod('2026-06',$before,[]);throw new RuntimeException('Accepted row deletion');}catch(InvalidArgumentException $e){}
$may=$before;$may['gb1:1'][4]=50;assertRawActualPeriod('2026-05',$before,$may);
assertRawActualPeriod('2026-06',[],['new'=>[1,2,3,4,5,6]]);
$old=['_products'=>['existing'=>[['name'=>'Dummy','price'=>10,'actual_unit'=>[1,2,3,4,5,6],'actual_hna'=>[10,20,30,40,50,60]]]]];
$new=$old;$new['_products']['existing'][0]['actual_unit'][4]=99;
try{assertRawActualPeriod('2026-06',ethicalActualPeriodValues($old),ethicalActualPeriodValues($new));throw new RuntimeException('Accepted Ethical May edit');}catch(InvalidArgumentException $e){}
echo "PASS dummy immutable prior/future months, current-month corrections, row deletion, first upload and Ethical price-derived HNA.\n";
