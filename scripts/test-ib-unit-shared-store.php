<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/unit_sell_out_targets.php';
function ibStoreCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
class IbUnitStoreConnection extends mysqli {
 public array $inputs=[],$targets=[];public ?array $before=null;public bool $fail=false;public function __construct(){parent::__construct();}
 public function prepare($sql):mysqli_stmt|false{return new IbUnitStoreStatement($this,$sql);}
 public function begin_transaction(int $flags=0,?string $name=null):bool{$this->before=[$this->inputs,$this->targets];return true;}
 public function commit(int $flags=0,?string $name=null):bool{$this->before=null;return true;}
 public function rollback(int $flags=0,?string $name=null):bool{if($this->before!==null)[$this->inputs,$this->targets]=$this->before;$this->before=null;return true;}
}
class IbUnitStoreStatement extends mysqli_stmt {
 private array $p=[];private ?array $result=null;public function __construct(private IbUnitStoreConnection $c,private string $sql){}
 public function bind_param(string $types,&...$vars):bool{$this->p=&$vars;return true;}
 public function execute(?array $params=null):bool{
  $p=$this->p;$year=(int)$p[0];$s=$this->sql;if(str_starts_with($s,'SELECT')){$this->result=$this->c->inputs[$year.'|ib']??null;return true;}
  ibStoreCheck($this->c->before!==null,'Write requires transaction');
  if(str_contains($s,'unit_sell_out_target_inputs')){if(str_starts_with($s,'DELETE'))unset($this->c->inputs[$year.'|'.$p[1]]);else $this->c->inputs[$year.'|'.$p[1]]=['payload'=>$p[2],'source_period'=>$p[3]];}
  elseif(str_starts_with($s,'DELETE')){foreach(array_keys($this->c->targets) as $key)if(str_starts_with($key,$year.'|ib|'))unset($this->c->targets[$key]);}
  else {if($this->c->fail)throw new RuntimeException('Simulated monthly write failure');$this->c->targets[$year.'|ib|'.$p[1]]=$p[2];}return true;
 }
 public function get_result():mysqli_result|false{return new IbUnitStoreResult($this->result);}
 public function close():true{return true;}
}
class IbUnitStoreResult extends mysqli_result {
 public function __construct(private ?array $record){}public function fetch_assoc():array|null|false{return $this->record;}
}
$conn=new IbUnitStoreConnection();$conn->inputs['2025|ib']=['payload'=>'preserve'];$conn->targets['2025|ib|1']='preserve';$conn->targets['2026|gb1|1']='preserve';
$parts=[];foreach(['A'=>100,'B'=>20,'actual_A'=>90,'actual_B'=>10] as $key=>$factor)foreach(range(1,12) as $month)$parts[$key][]=$month*$factor;
$save=function($data)use($conn){$conn->begin_transaction();try{saveIbUnitInputs($conn,2026,'2026-09',$data);$conn->commit();}catch(Throwable $e){$conn->rollback();throw $e;}};$save($parts);
ibStoreCheck(count($conn->targets)===14&&$conn->targets['2026|ib|1']==='120.000000'&&$conn->targets['2026|ib|12']==='120.000000','Canonical monthly target persisted');
$loaded=loadIbUnitHistory($conn,2026);ibStoreCheck($loaded['actual_A'][0]===90&&$loaded['A'][11]===1200,'Target/actual cumulative cells persisted for both templates');
$before=[$conn->inputs,$conn->targets];$save($parts);ibStoreCheck([$conn->inputs,$conn->targets]===$before,'Repeat upload duplicates data');
$incoming=['A'=>array_fill(0,12,null),'B'=>array_fill(0,12,null),'actual_A'=>array_fill(0,12,null),'actual_B'=>array_fill(0,12,null)];$incoming['actual_A'][0]=0;$save($incoming);$loaded=loadIbUnitHistory($conn,2026);ibStoreCheck($loaded['actual_A'][0]===0&&$loaded['A'][11]===1200,'Partial actual upload retains target and explicit zero');
$before=[$conn->inputs,$conn->targets];$conn->fail=true;try{$save($parts);throw new LogicException('Expected failure');}catch(RuntimeException $e){ibStoreCheck([$conn->inputs,$conn->targets]===$before,'Failure must roll back shared input and monthly totals');}
echo "PASS shared IB database transaction: cumulative target/actual, canonical monthly totals, repeat no duplicates, partial/zero preservation, other years/GB and rollback (DB double only)\n";
