<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/biaya_workbook.php';require __DIR__.'/../includes/unit_revenue_input_template.php';
$GLOBALS['reportAccessUser']='pharma';
class ScopeQuery {
 public $sql,$db,$args=[];function __construct($db,$sql){$this->db=$db;$this->sql=$sql;}
 function bind_param($types,&...$args){$this->args=&$args;return true;}
 function execute(){
  $this->db->queries[]=$this->sql;
  if(str_starts_with($this->sql,'DELETE FROM biaya')){if(!str_contains($this->sql,'gb IN (1,4)'))throw new RuntimeException('Unscoped deletion');$this->db->rows=array_values(array_filter($this->db->rows,fn($row)=>!in_array($row['gb'],[1,4])));}
  if(str_starts_with($this->sql,'INSERT INTO biaya ('))$this->db->rows[]=['gb'=>$this->args[2],'value'=>$this->args[6]];
  if(str_starts_with($this->sql,'INSERT INTO unit_revenue_input_snapshots'))$this->db->snapshot=$this->args[1];
  return true;
 }
 function get_result(){return new class($this->db->snapshot){private $payload;function __construct($payload){$this->payload=$payload;}function fetch_assoc(){return $this->payload?['payload'=>$this->payload]:null;}};}
 function close(){}
}
class ScopeDb{public $rows=[['gb'=>1,'value'=>1],['gb'=>2,'value'=>999],['gb'=>4,'value'=>4],['gb'=>6,'value'=>666]],$queries=[],$snapshot;function query($sql){return true;}function prepare($sql){return new ScopeQuery($this,$sql);}function begin_transaction(){}function commit(){}function rollback(){}}
$db=new ScopeDb();saveBiayaProducts($db,'2026-10',[['type'=>'target','gb'=>1,'category'=>'EXIS','number'=>1,'name'=>'Dummy','values'=>array_fill(0,12,10)]]);
if($db->rows!==[['gb'=>2,'value'=>999],['gb'=>6,'value'=>666],['gb'=>1,'value'=>10]])throw new RuntimeException('Other GB changed');
$db->snapshot=gzencode(json_encode([['gb'=>'1','name'=>'old'],['gb'=>'2','name'=>'retained'],['__kind'=>'ethical_revenue','rev'=>[123]]]));
saveUnitRevenueTemplateSnapshot($db,'2026-10',[['gb'=>'4','name'=>'new']]);$saved=json_decode(gzdecode($db->snapshot),true);
if($saved!==[['gb'=>'2','name'=>'retained'],['__kind'=>'ethical_revenue','rev'=>[123]],['gb'=>'4','name'=>'new']])throw new RuntimeException('Snapshot lost other group');
echo "PASS fake-DB scoped BIAYA replacement and UNIT/Revenue snapshot merge preserve other GB/Ethical records exactly. No live DB accessed.\n";
