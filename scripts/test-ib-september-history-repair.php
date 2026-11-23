<?php
require __DIR__.'/../includes/ib_history_merge.php';
$fields=['target_ib_fm','actual_ib_fm','target_ib_fd','actual_ib_fd','target_new_ib_fm','actual_new_ib_fm','target_new_ib_fd','actual_new_ib_fd','target_mcm','actual_mcm'];
$primary=[];$history=[];$raw=[];
foreach(range(1,12) as $m){
 $row=array_merge(array_fill_keys($fields,null),['report_period'=>'2026-10','month_number'=>$m]);
 if($m<=8)foreach($fields as $i=>$field)$row[$field]=($i+1)*10;
 $primary[]=$row;
}
$map=[['revenue_exist_cum','IB_FM','actual_ib_fm',10],['revenue_exist_cum','IB_FD','actual_ib_fd',20],['revenue_new_cum','IB_FM','actual_new_ib_fm',0],['revenue_new_cum','IB_FD','actual_new_ib_fd',30],['mcm_cum','GROUP','actual_mcm',40]];
foreach($map as [$metric,$unit,$field,$monthly]){
 foreach([8,9] as $m)$raw[]=['report_period'=>$m===8?'2026-08':'2026-09','month_number'=>$m,'metric_code'=>$metric,'business_unit'=>$unit,'value_type'=>'ACTUAL','amount'=>$monthly*$m];
}
$history[]=array_merge(array_fill_keys($fields,null),['report_period'=>'2026-09','month_number'=>9,'actual_ib_fd'=>20]);
$merged=mergeIbMonthlyHistory($primary,$history,'2026-10');
$fixed=repairIbMonthlyFromCumulative($merged,$raw,'2026-10');
foreach(range(0,7) as $i)if($fixed[$i]!==$primary[$i])throw new RuntimeException('January-August changed');
foreach($map as [$metric,$unit,$field,$monthly])if((float)$fixed[8][$field]!== (float)$monthly)throw new RuntimeException('September missing: '.$field);
foreach(range(9,11) as $i)foreach($fields as $field)if(str_starts_with($field,'actual_')&&$fixed[$i][$field]!==null)throw new RuntimeException('Future actual was invented');
$zero=$primary;$zero[8]['actual_ib_fm']=0;
if(mergeIbMonthlyHistory($zero,[array_merge($zero[8],['actual_ib_fm'=>99])],'2026-10')[8]['actual_ib_fm']!==0)throw new RuntimeException('Zero overwritten');
$limited=repairIbMonthlyFromCumulative($primary,$raw,'2026-08');
if($limited[8]['actual_ib_fm']!==null)throw new RuntimeException('Future upload leaked into August');
// The synthetic fixture contains no user values and feeds the export regression.
file_put_contents('/tmp/ib-synthetic-september.json',json_encode($fixed));
echo "PASS synthetic IB: September recovered for all actual series; January-August unchanged; October-December blank; zero and period isolation retained.\n";
