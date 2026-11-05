<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/unit_sell_out_targets.php';
function checkTargetStore($test,$message){if(!$test)throw new RuntimeException($message);}
class UnitTargetStoreConnection extends mysqli {
    public array $targets=[],$inputs=[],$events=[];public ?array $before=null;public bool $failInputs=false;
    public function __construct(){}
    #[ReturnTypeWillChange] public function query($sql,$mode=MYSQLI_STORE_RESULT){checkTargetStore($this->before===null,'DDL inside transaction');$this->events[]='DDL';return true;}
    #[ReturnTypeWillChange] public function begin_transaction($flags=0,$name=null){$this->before=[$this->targets,$this->inputs];$this->events[]='BEGIN';return true;}
    #[ReturnTypeWillChange] public function commit($flags=0,$name=null){$this->before=null;$this->events[]='COMMIT';return true;}
    #[ReturnTypeWillChange] public function rollback($flags=0,$name=null){if($this->before)[$this->targets,$this->inputs]=$this->before;$this->before=null;$this->events[]='ROLLBACK';return true;}
    #[ReturnTypeWillChange] public function prepare($sql){return new UnitTargetStoreStatement($this,$sql);}
}
class UnitTargetStoreStatement {
    private array $params=[];
    public function __construct(private UnitTargetStoreConnection $conn,private string $sql){}
    public function bind_param($types,&...$params){$this->params=&$params;return true;}
    public function close(){}
    public function execute(){
        checkTargetStore($this->conn->before!==null,'Write outside transaction');$p=$this->params;$prefix=$p[0].':'.$p[1];
        if(str_contains($this->sql,'unit_sell_out_target_inputs')){
            if(str_starts_with($this->sql,'DELETE'))unset($this->conn->inputs[$prefix]);
            else {if($this->conn->failInputs)throw new RuntimeException('Simulated snapshot failure');$this->conn->inputs[$prefix]=['report_year'=>$p[0],'product'=>$p[1],'payload'=>$p[2],'source_period'=>$p[3]];}
        }elseif(str_starts_with($this->sql,'DELETE')){
            foreach(array_keys($this->conn->targets) as $key)if(str_starts_with($key,$prefix.':'))unset($this->conn->targets[$key]);
        }else{$this->conn->targets[$prefix.':'.$p[2].':'.$p[3]]=$p[4];}
        return true;
    }
}
// Execute the real API transaction block, not a duplicate of its write logic.
$source=file_get_contents(__DIR__.'/../dashboard/unit_sell_out_targets_api.php');
$start=strpos($source,'  $products=array_values(');$end=strpos($source,' }else{',$start);$program=substr($source,$start,$end-$start);
checkTargetStore($start!==false&&$end!==false,'Transaction block not found');
if(in_array('--products',$argv,true)){
 $book=buildUnitSellOutProductTemplate('2026-11',12,2);$s=$book->getSheetByName('GB 1-6');$r=6;
 for($gb=1;$gb<=6;$gb++)foreach(['A','D'] as $cat){$s->setCellValue('A'.$r,$gb);$s->setCellValue('C'.$r,'Synthetic '.$gb.$cat);$s->setCellValue('D'.$r,$cat);$s->setCellValue('F'.$r,10);$r++;}
 $book->getSheetByName('ETHICAL')->setCellValue('C6',7);
 $book->getSheetByName('IB')->setCellValue('C6',10);$book->getSheetByName('IB')->setCellValue('C7',2);
}else{
 $book=buildUnitSellOutSummaryTemplate('2026-11');$s=$book->getSheetByName('TARGET UNIT SELL OUT');
 foreach(unitSellOutInputRows() as $rows)foreach($rows as $row)for($m=0;$m<12;$m++)$s->setCellValue([$m+3,$row],$m+1);
}

$inputData=parseUnitSellOutInputTemplate($book,2026);$records=$inputData['records'];$year=2026;$period='2026-11';$conn=new UnitTargetStoreConnection();
$conn->targets=['2025:gb1:existing:1'=>'preserve'];$conn->inputs=['2025:gb1'=>['preserve']];
$upload=function()use(&$conn,&$inputData,&$records,&$year,&$period,$program){ob_start();try{eval($program);return json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){$conn->rollback();throw $e;}finally{ob_end_clean();}};
$result=$upload();checkTargetStore($result['success']&&$result['input_template']&&count($conn->targets)===181&&count($conn->inputs)===9,'Combined target and input records not stored');
foreach($inputData['inputs'] as $product=>$parts)checkTargetStore(json_decode($conn->inputs['2026:'.$product]['payload'],true)===$parts,'Editable product details or cumulative input not stored exactly');
$before=[$conn->targets,$conn->inputs];$upload();checkTargetStore([$conn->targets,$conn->inputs]===$before,'Repeat upload creates duplicates or changes another year');
$conn->failInputs=true;$inputData['inputs']['gb1']['A'][0]=999;
try{$upload();throw new LogicException('Failure was not raised');}catch(RuntimeException $e){checkTargetStore([$conn->targets,$conn->inputs]===$before,'Snapshot failure did not roll back target replacement');}
$conn->failInputs=false;$inputData=null;$records=array_values(array_filter($records,fn($r)=>$r['product']==='gb1'));
$result=$upload();checkTargetStore(!$result['input_template']&&!isset($conn->inputs['2026:gb1'])&&isset($conn->inputs['2026:ib']),'Legacy upload must invalidate only its replaced input group');
checkTargetStore($conn->targets['2025:gb1:existing:1']==='preserve'&&isset($conn->inputs['2025:gb1']),'Another year changed');
echo "PASS one-file target/input transaction, repeat uploads without duplicates, rollback on input-store failure, legacy group invalidation and year isolation (DB double; no production writes)\n";
