<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/unit_sell_out_targets.php';require __DIR__.'/../includes/ethical_source_template.php';
function checkSharedStore($test,$message){if(!$test)throw new RuntimeException($message);}
class SharedEthicalConnection extends mysqli {
 public array $data=['inputs'=>[],'monthly'=>[],'targets'=>[],'unit_inputs'=>[]];public ?array $before=null;public string $fail='';
 public function __construct(){parent::__construct();}
 #[ReturnTypeWillChange]public function query($sql,$mode=MYSQLI_STORE_RESULT){checkSharedStore($this->before===null,'DDL must precede transaction');return true;}
 #[ReturnTypeWillChange]public function prepare($sql){return new SharedEthicalStatement($this,$sql);}
 public function begin_transaction(int $flags=0,?string $name=null):bool{$this->before=$this->data;return true;}
 public function commit(int $flags=0,?string $name=null):bool{$this->before=null;return true;}
 public function rollback(int $flags=0,?string $name=null):bool{if($this->before!==null)$this->data=$this->before;$this->before=null;return true;}
}
class SharedEthicalStatement {
 private array $p=[],$result=[];
 public function __construct(private SharedEthicalConnection $c,private string $sql){}
 public function bind_param($types,&...$p){$this->p=&$p;return true;}
 public function close(){}
 public function get_result(){return new class($this->result){public function __construct(private array $rows){}public function fetch_all($mode){return $this->rows;}};}
 public function execute(){
  $p=$this->p;$year=(int)$p[0];$s=$this->sql;
  if(str_starts_with($s,'SELECT')){$this->result=array_values(array_filter($this->c->data['monthly'],fn($r)=>$r['report_year']===$year));return true;}
  checkSharedStore($this->c->before!==null,'Write outside transaction');if($this->c->fail!==''&&str_starts_with($s,$this->c->fail))throw new RuntimeException('Simulated store failure');
  if(str_contains($s,'template ethical'))$this->c->data['inputs'][$year]=['report_year'=>$year,'payload'=>$p[1],'source_period'=>$p[2]];
  elseif(str_contains($s,'ethical HNA dan UNIT')){
   if(str_starts_with($s,'DELETE'))$this->c->data['monthly']=array_filter($this->c->data['monthly'],fn($r)=>$r['report_year']!==$year);
   else $this->c->data['monthly'][$year.'|'.$p[1].'|'.$p[2]]=['report_year'=>$year,'kind'=>$p[1],'month'=>$p[2],'unit_target'=>$p[3],'hna_target'=>$p[4]];
  }elseif(str_contains($s,'unit_sell_out_target_inputs')){
   $key=$year.'|'.$p[1];if(str_starts_with($s,'DELETE'))unset($this->c->data['unit_inputs'][$key]);else $this->c->data['unit_inputs'][$key]=['report_year'=>$year,'product'=>$p[1],'payload'=>$p[2],'source_period'=>$p[3]];
  }elseif(str_contains($s,'target unit sell out')){
   if(str_starts_with($s,'DELETE'))$this->c->data['targets']=array_filter($this->c->data['targets'],fn($r)=>$r['report_year']!==$year||$r['product']!=='ethical');
   else $this->c->data['targets'][$year.'|ethical|'.$p[1].'|'.$p[2]]=['report_year'=>$year,'product'=>'ethical','kind'=>$p[1],'month'=>$p[2],'units'=>$p[3]];
  }else throw new RuntimeException('Unexpected SQL in source test');return true;
 }
}
$b=buildEthicalSourceTemplate('2026-09');$s=$b->getSheet(0);foreach(ethicalProductBlocks() as $kind=>$block){$r=$block['first'];$s->setCellValue('B'.$r,'Synthetic');$s->setCellValue('D'.$r,1000);$s->setCellValue('G'.$r,7);for($m=0;$m<12;$m++){$s->setCellValue([5+$m*4,$r],$m===11?-2:$m+1);}}
$data=parseEthicalSourceTemplate($b,'2026-09');$conn=new SharedEthicalConnection();
$conn->data['targets']=['old'=>['report_year'=>2025,'product'=>'ethical','units'=>'preserve'],'gb'=>['report_year'=>2026,'product'=>'gb1','units'=>'preserve'],'ib'=>['report_year'=>2026,'product'=>'ib','units'=>'preserve']];
$save=function($payload)use($conn){$conn->begin_transaction();try{saveEthicalSource($conn,'2026-09',$payload);$conn->commit();}catch(Throwable $e){$conn->rollback();throw $e;}};
ensureEthicalSourceStore($conn);$save($data);
checkSharedStore($conn->data['targets']['2026|ethical|existing|12']['units']==='-4.000000'&&$conn->data['monthly']['2026|existing|12']['hna_target']==='-4000.000000','Negative UNIT/HNA correction not stored');
checkSharedStore(count($conn->data['monthly'])===24&&count($conn->data['targets'])===27&&count($conn->data['inputs'])===1,'Source did not populate both canonical destinations');
checkSharedStore($conn->data['targets']['2026|ethical|existing|1']['units']==='2.000000'&&$conn->data['monthly']['2026|existing|1']['hna_target']==='2000.000000','UNIT and HNA crossed or scaled incorrectly');
checkSharedStore($conn->data['targets']['gb']['units']==='preserve'&&$conn->data['targets']['ib']['units']==='preserve'&&$conn->data['targets']['old']['units']==='preserve','Source upload changed other GB/IB/year');
checkSharedStore(json_decode($conn->data['inputs'][2026]['payload'],true)['actual_unit']['existing'][0]===7.0,'REAL values not persisted');
checkSharedStore(isset(json_decode($conn->data['inputs'][2026]['payload'],true)['_products']['existing'][0]['name']),'Product details not persisted');
$before=$conn->data;$save($data);checkSharedStore($conn->data===$before,'Repeat upload creates duplicate data');
$changed=$data;$changed['inputs']['unit']['existing'][0]=999;$changed['summary']=ethicalSourceSummary($changed['inputs']);$conn->fail='INSERT INTO `target unit sell out`';
try{$save($changed);throw new LogicException('Expected source failure');}catch(RuntimeException $e){checkSharedStore($conn->data===$before,'UNIT failure did not roll back HNA and source inputs');}$conn->fail='';
$stale=['records'=>[['report_year'=>2026,'product'=>'gb1','kind'=>'existing','month'=>1,'units'=>'77'],['report_year'=>2026,'product'=>'ethical','kind'=>'existing','month'=>1,'units'=>'999999']],'inputs'=>['ethical'=>['existing'=>array_fill(0,12,999999),'new'=>array_fill(0,12,999999)]]];
$conn->begin_transaction();$resolved=applyEthicalSourceTargets($conn,2026,$stale);$conn->commit();checkSharedStore($resolved['ethical_from_source']&&$resolved['records'][0]===$stale['records'][0]&&$resolved['inputs']['ethical']['existing'][0]===2.0,'Stale target upload overwrote the current master or another GB');
$history=array_values($conn->data['unit_inputs']);$target=buildUnitSellOutProductWithHistory('2026-12',$history);checkSharedStore($target->getSheetByName('ETHICAL')->getCell('C6')->getValue()===2.0,'Target template did not receive the source UNIT summary');
$newYear=applyEthicalSourceTargets($conn,2027,$stale);checkSharedStore(!isset($newYear['ethical_from_source']),'Source data leaked into another year');
echo "PASS shared Ethical DB double: one source -> HNA + UNIT + template reuse, no duplicates, rollback across destinations, other GB/IB/year preserved and stale target copies overridden\n";
