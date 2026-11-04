<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_template_history.php';
require __DIR__.'/../includes/biaya_workbook.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
function verifyBiaya($ok,$message){if(!$ok)throw new RuntimeException($message);}
$records=[];
foreach(range(1,31) as $number)foreach(['target','actual'] as $type){
 $records[]=['report_year'=>2026,'report_period'=>'2026-11','jenis'=>$type,'gb'=>1,'kategori'=>$number%2?'EXIS':'D','nomor_produk'=>$number,'nama_produk'=>'Synthetic '.$number,'jan'=>100,'feb'=>0,'mar'=>null,'oct'=>10,'nov'=>11,'dec'=>null];
}
$records[]=['report_year'=>2026,'report_period'=>'2026-11','jenis'=>'target','gb'=>5,'kategori'=>'EXIS','nomor_produk'=>7,'nama_produk'=>'Synthetic GB5','jan'=>3250,'dec'=>6000];
$records[]=['report_year'=>2026,'report_period'=>'2026-11','jenis'=>'actual','gb'=>6,'kategori'=>'D','nomor_produk'=>9,'nama_produk'=>'Actual only','jan'=>5];
$book=buildBiayaTemplateWithHistory('2026-12',$records);
$target=$book->getSheetByName('TARGET');$actual=$book->getSheetByName('REALISASI');
verifyBiaya($target->getCell('D6')->getValue()==='Synthetic 1','Saved identity restored');
verifyBiaya($actual->getCell('D6')->getCalculatedValue()==='Synthetic 1','Actual identity remains linked');
verifyBiaya($target->getCell('E6')->getValue()===100.0,'Saved target restored');
verifyBiaya($actual->getCell('F6')->getValue()===0.0,'Saved zero restored');
verifyBiaya($actual->getCell('G6')->getValue()===null,'Saved blank restored');
$rows=parseBiayaProducts($book,2026);
$gb5=array_values(array_filter($rows,fn($r)=>$r['type']==='target'&&$r['gb']===5))[0];
verifyBiaya($gb5['values'][0]===3250.0,'GB5 roundtrip retains storage units exactly');
verifyBiaya(count(array_filter($rows,fn($r)=>$r['gb']===1&&$r['type']==='target'))===31,'Extra product slots expand without loss');
verifyBiaya(count(array_filter($rows,fn($r)=>$r['gb']===6&&$r['name']==='Actual only'))===2,'Actual-only identity supplied through Target too');
$path=tempnam(sys_get_temp_dir(),'biaya-carry-');
try{IOFactory::createWriter($book,'Xlsx')->save($path);$loaded=IOFactory::load($path);verifyBiaya(count(parseBiayaProducts($loaded,2026))===count($rows),'Download can be uploaded again');}finally{unlink($path);}
$oct=buildBiayaTemplateWithHistory('2026-10',$records);
verifyBiaya($oct->getSheetByName('TARGET')->getCell('N6')->getValue()===10.0,'Earlier selected month restored');
verifyBiaya($oct->getSheetByName('TARGET')->getCell('O6')->getValue()===null,'Later month hidden in earlier template');
foreach(['2026-01','2027-01','2027-02'] as $p){
 $blank=buildBiayaTemplateWithHistory($p,$records);verifyBiaya($blank->getSheetByName('TARGET')->getCell('D6')->getValue()===null,'January reset and year isolation');
}
$prior=$records;foreach($prior as &$r){$r['report_year']=2025;$r['report_period']='2025-11';}unset($r);
$rolling=buildBiayaTemplateWithHistory('2025-12',$prior);verifyBiaya($rolling->getSheetByName('TARGET ROLLING')->getCell('E6')->getValue()===100.0,'2025 uses rolling sheet');
echo "PASS BIAYA November-to-December carry, identities and formulas, target/actual, zero/blank, expanded products, units, upload roundtrip, earlier months, January reset and year isolation\n";
