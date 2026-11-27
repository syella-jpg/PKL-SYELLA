<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/upload_precheck.php';require __DIR__.'/../includes/biaya_workbook.php';
$GLOBALS['reportAccessUser']='admin 1';
class PrecheckDb extends mysqli {
 public array $queries=[],$biaya=[],$locks=[],$native=[],$unit=[];
 public function __construct(){}
 public function prepare(string $sql):mysqli_stmt|false{if(!str_starts_with($sql,'SELECT '))throw new RuntimeException('Unexpected mutation');$this->queries[]=$sql;$rows=str_contains($sql,'FROM report_upload_files')?$this->locks:(str_contains($sql,'FROM biaya')?$this->biaya:(str_contains($sql,'FROM `upb non bj`')?$this->native:(str_contains($sql,'FROM unit_sell_out_target_inputs')?$this->unit:[])));return new PrecheckStatement($rows);}
}
class PrecheckStatement extends mysqli_stmt {
 public function __construct(private array $rows){}
 public function bind_param(string $types,&...$vars):bool{return true;}
 public function execute(?array $params=null):bool{return true;}
 public function get_result():mysqli_result|false{return new PrecheckResult($this->rows);}
 public function close():true{return true;}
}
class PrecheckResult extends mysqli_result {public function __construct(private array $rows){}public function fetch_all(int $mode=MYSQLI_NUM):array{return $this->rows;}}
function precheckAssert($ok,$message){if(!$ok)throw new RuntimeException($message);}
function precheckReject(callable $f){try{$f();}catch(InvalidArgumentException $e){precheckAssert(str_starts_with($e->getMessage(),'UPLOAD DITOLAK!'),'Expected rejection prefix');return;}throw new RuntimeException('Tampered input passed');}
function precheckWrite($b,$p){$w=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($b);$w->setPreCalculateFormulas(false);$w->save($p);}
$db=new PrecheckDb();$book=buildBiayaInputTemplate('2026-06');$target=$book->getSheetByName('TARGET');$layout=locateBiayaInputTables($target,true)[0];$r=$layout['first'];
foreach(['category'=>'EXIS','number'=>1,'name'=>'Dummy product'] as $key=>$v)$target->setCellValue([$layout['identity'][$key],$r],$v);
$target->setCellValue([$layout['months'][0],$r],100);
$actual=$book->getSheetByName('REALISASI');$a=locateBiayaInputTables($actual,true)[0];$actual->setCellValue([$a['months'][0],$r],10);$actual->setCellValue([$a['months'][5],$r],20);
$parsed=parseBiayaProducts($book,2026);$months=['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
foreach($parsed as $v){$stored=['jenis'=>$v['type'],'gb'=>$v['gb'],'nomor_produk'=>$v['number'],'kategori'=>$v['category']];foreach($months as $m=>$key)$stored[$key]=$v['values'][$m];$db->biaya[]=$stored;}
$before=$db->biaya;$p=tempnam('/tmp','dummy-precheck-');
try{
 precheckWrite($book,$p);uploadPrecheckInput($db,'BIAYA','2026-06',$p);precheckAssert($db->biaya===$before,'Precheck changed baseline');
 $actual->setCellValue([$a['months'][5],$r],30);precheckWrite($book,$p);uploadPrecheckInput($db,'BIAYA','2026-06',$p);
 $target->setCellValue([$layout['months'][0],$r],999);$target->getProtection()->setSheet(true)->setPassword('relocked');precheckWrite($book,$p);precheckReject(fn()=>uploadPrecheckInput($db,'BIAYA','2026-06',$p));
 $target->setCellValue([$layout['months'][0],$r],100);$actual->setCellValue([$a['months'][0],$r],999);precheckWrite($book,$p);precheckReject(fn()=>uploadPrecheckInput($db,'BIAYA','2026-06',$p));
 $db->locks=[['locked'=>1]];precheckReject(fn()=>uploadPrecheckInput($db,'BIAYA','2026-06',$p));$db->locks=[];
 precheckReject(fn()=>uploadPrecheckInput($db,'TARGET REVENUE','2026-06',$p));
 precheckAssert($db->biaya===$before,'Rejected precheck changed baseline');
 try{uploadPrecheckRows($db,'DELETE FROM biaya');throw new RuntimeException('Mutation allowed');}catch(LogicException $expected){}
}finally{$book->disconnectWorksheets();unlink($p);}
echo "PASS generated dummy BIAYA precheck: unchanged/current data accepted, relocked target and past actual rejected, locked period blocked, baseline unchanged and SELECT-only queries. Mock DB only.\n";

require_once __DIR__.'/../includes/report_input_templates.php';
$b=buildReportInputTemplate('IB','2026-09');$gm=getIbInputSheet($b,'GM IB');$r=ibUnitTemplateRows(true);
$gm->setCellValue('F'.$r['A'],100);$gm->setCellValue('F'.$r['actual_A'],10);$gm->setCellValue('N'.$r['actual_A'],20);
$parts=readIbUnitTable($gm,true);$db->unit=[['payload'=>json_encode($parts)]];$p=tempnam('/tmp','dummy-ib-precheck-');
try{precheckWrite($b,$p);uploadPrecheckInput($db,'IB','2026-09',$p);$gm->setCellValue('F'.$r['actual_A'],999);precheckWrite($b,$p);precheckReject(fn()=>uploadPrecheckInput($db,'IB','2026-09',$p));}
finally{$b->disconnectWorksheets();unlink($p);}
echo "PASS generated September IB precheck: original template accepted, shared UNIT past-data tampering rejected. Mock DB only.\n";
