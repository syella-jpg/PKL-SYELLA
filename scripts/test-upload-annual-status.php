<?php
require __DIR__.'/../includes/upload_file_locks.php';
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach(['2026','2027'] as $year)foreach(['01','06','12'] as $month){
 $period=$year.'-'.$month;
 foreach(['ETHICAL','TARGET UNIT SELL OUT','TARGET REVENUE','REVENUE VERSI EDP MNJ Target'] as $section)verify(uploadIsAnnual($section),'Annual classification');
 verify(uploadMetadataPredicate('ETHICAL','ETHICAL',['report_period'],$period)===['report_period BETWEEN ? AND ?','ss',[$year.'-01',$year.'-12']],'Ethical applies to all months of same year');
 verify(uploadMetadataPredicate('ETHICAL','template ethical',['report_year'],$period)===['`report_year`=?','s',[$year]],'Native annual Ethical metadata');
 verify(uploadMetadataPredicate('TARGET UNIT SELL OUT','target unit sell out',['report_year'],$period)===['`report_year`=?','s',[$year]],'Annual Unit target');
 verify(uploadMetadataPredicate('TARGET REVENUE','target_revenue',['product'],$period)===['product LIKE ?','s',[$year.' | %']],'Revenue target year prefix');
 verify(uploadMetadataPredicate('REVENUE VERSI EDP MNJ Target','revenue_edp_mnj_target',['periode_tahun'],$period)===['`periode_tahun`=?','s',[$year]],'EDP target year');
 verify(uploadMetadataPredicate('DAFTAR','daftar',['report_period'],$period)===['report_period=?','s',[$period]],'Monthly sections unchanged');
}
verify(!uploadIsAnnual('DAFTAR')&&!uploadIsAnnual('LAUNCH'),'Annual exceptions limited to requested sections');
$code=file_get_contents(__DIR__.'/../includes/upload_file_locks.php');verify(str_contains($code,'AND has_upload=0 AND unlocked_by IS NULL'),'Do not re-lock explicitly unlocked annual slots');
echo "PASS dummy annual status for Jan/Jun/Dec 2026/2027, four requested sections, native year schemas, monthly isolation, explicit unlock protection. No DB accessed.\n";
