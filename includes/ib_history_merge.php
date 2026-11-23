<?php
/** Fill missing monthly fields only; populated snapshot values (including zero) win. */
function mergeIbMonthlyHistory(array $primary,array $history,string $period): array
{
 $year=substr($period,0,4);$last=(int)substr($period,5,2);
 $fields=['target_ib_fm','actual_ib_fm','target_ib_fd','actual_ib_fd','target_new_ib_fm','actual_new_ib_fm','target_new_ib_fd','actual_new_ib_fd','target_mcm','actual_mcm'];
 $byMonth=[];$source=$primary[0]['report_period']??$period;
 foreach($primary as $row)$byMonth[(int)$row['month_number']]=$row;
 usort($history,static fn($a,$b)=>strcmp($b['report_period'],$a['report_period']));
 foreach($history as $row){
  $p=(string)$row['report_period'];$m=(int)$row['month_number'];
  if(substr($p,0,4)!==$year||$p>$period||$m<1||$m>12)continue;
  if(!isset($byMonth[$m]))$byMonth[$m]=array_merge(array_fill_keys($fields,null),['report_period'=>$source,'month_number'=>$m]);
  foreach($fields as $field){
   if(str_starts_with($field,'actual_')&&$m>$last)continue;
   if(($byMonth[$m][$field]??null)===null&&($row[$field]??null)!==null)$byMonth[$m][$field]=$row[$field];
  }
 }
 ksort($byMonth);return array_values($byMonth);
}

/** Recover a missing monthly difference from original cumulative cells across uploads. */
function repairIbMonthlyFromCumulative(array $records,array $cumulative,string $period): array
{
 $year=substr($period,0,4);$last=(int)substr($period,5,2);$raw=[];
 usort($cumulative,static fn($a,$b)=>strcmp($b['report_period'],$a['report_period']));
 foreach($cumulative as $row){
  $p=(string)$row['report_period'];$m=(int)$row['month_number'];$amount=$row['amount']??null;
  if(substr($p,0,4)!==$year||$p>$period||$m<1||$m>12||$amount===null||!is_numeric($amount))continue;
  $key=$row['metric_code'].'|'.$row['business_unit'].'|'.$row['value_type'];
  if(!isset($raw[$key][$m]))$raw[$key][$m]=(float)$amount;
 }
 $mapping=[
  ['revenue_exist_cum','IB_FM','target_ib_fm','actual_ib_fm'],
  ['revenue_exist_cum','IB_FD','target_ib_fd','actual_ib_fd'],
  ['revenue_new_cum','IB_FM','target_new_ib_fm','actual_new_ib_fm'],
  ['revenue_new_cum','IB_FD','target_new_ib_fd','actual_new_ib_fd'],
  ['mcm_cum','GROUP','target_mcm','actual_mcm']
 ];
 foreach($records as &$record){
  $m=(int)$record['month_number'];
  foreach($mapping as [$metric,$unit,$target,$actual])foreach(['TARGET'=>$target,'ACTUAL'=>$actual] as $type=>$field){
   if(($record[$field]??null)!==null||($type==='ACTUAL'&&$m>$last))continue;
   $values=$raw[$metric.'|'.$unit.'|'.$type]??[];
   if(isset($values[$m])&&($m===1||isset($values[$m-1])))$record[$field]=$values[$m]-($m===1?0:$values[$m-1]);
  }
 }unset($record);
 return $records;
}
