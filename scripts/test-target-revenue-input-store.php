<?php
namespace TargetRevenueStoreTest;
use Throwable;
use mysqli;
use RuntimeException;
use InvalidArgumentException;
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/target_revenue_input_template.php';
class Connection extends \mysqli {
    public array $canonical=[],$details=[],$backup=[];public bool $failDetail=false;public int $commits=0,$rollbacks=0;
    public function __construct(){parent::__construct();}
    public function set_charset(string $charset):bool{return true;}
    #[\ReturnTypeWillChange] public function query($query,$mode=MYSQLI_STORE_RESULT){return true;}
    #[\ReturnTypeWillChange] public function prepare($query){return new Statement($this,$query);}
    public function begin_transaction(int $flags=0,?string $name=null):bool{$this->backup=[$this->canonical,$this->details];return true;}
    public function commit(int $flags=0,?string $name=null):bool{$this->commits++;return true;}
    public function rollback(int $flags=0,?string $name=null):bool{[$this->canonical,$this->details]=$this->backup;$this->rollbacks++;return true;}
    public function close():bool{return true;}
}
class Statement {
    private array $params=[];
    public function __construct(private Connection $conn,private string $sql){}
    public function bind_param($types,&...$values){$this->params=&$values;}
    public function execute(){
        $p=[];foreach($this->params as $value)$p[]=$value;$c=$this->conn;
        if(str_starts_with($this->sql,'INSERT IGNORE'))$c->details[$p[0]]??=null;
        elseif(str_starts_with($this->sql,'DELETE FROM target_revenue')){foreach(array_keys($c->canonical) as $key)if(str_starts_with($key,substr($p[0],0,-1)))unset($c->canonical[$key]);}
        elseif(str_starts_with($this->sql,'INSERT INTO target_revenue '))$c->canonical[$p[0]]=array_values($p);
        elseif(str_starts_with($this->sql,'UPDATE target_revenue_inputs')){if($c->failDetail)throw new \RuntimeException('Synthetic detail failure');$c->details[$p[1]]=$p[0];}
        return true;
    }
    public function get_result(){return new Result(['payload'=>$this->conn->details[$this->params[0]]??null,'report_year'=>$this->params[0]]);}
    public function close(){}
}
class Result {public function __construct(private array $row){}public function fetch_assoc(){return $this->row;}}
$connection=new Connection();
function openReportConnection(){global $connection;return $connection;}
// Only replace the HTTP upload origin check; execute the real parser and save handler.
function is_uploaded_file($path){return is_file($path);}
function file_get_contents($path){return $path==='php://input' ? ($GLOBALS['syntheticRevenueJson']??'') : \file_get_contents($path);}
$source=file_get_contents(__DIR__.'/../dashboard/revenue_so_targets_api.php');
$source=preg_replace('/^<\?php\s*/','',$source);
$source=str_replace("require_once __DIR__ . '/../includes/database.php';",'',$source);
function runUpload($source,$path):array {
    $_SERVER['REQUEST_METHOD']='POST';$_FILES=['file'=>['name'=>'synthetic.xlsx','tmp_name'=>$path,'size'=>filesize($path),'error'=>UPLOAD_ERR_OK]];$_POST=['period'=>'2026-09'];
    http_response_code(200);ob_start();try{eval('namespace '.__NAMESPACE__.';use \Throwable;use \mysqli;use \RuntimeException;use \InvalidArgumentException;'.$source);$json=ob_get_contents();}finally{ob_end_clean();}return json_decode($json,true,512,JSON_THROW_ON_ERROR);
}
function check($value,$label){if(!$value)throw new \RuntimeException($label);}
$book=\buildTargetRevenueInputTemplate('2026-01');$sheet=$book->getSheetByName('GB 1');
$sheet->setCellValue('B8','SYNTHETIC')->setCellValue('C8',12)->setCellValue('N8',8);
$path=tempnam('/tmp','target-api-');
try {
    $writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);$writer->setPreCalculateFormulas(false);$writer->save($path);
    $saved=runUpload($source,$path);check($saved['success']??false,'API upload succeeds');
    check(count($connection->canonical)===30,'all groups saved once');
    check($connection->canonical['2026 | GB 1 | Kategori A'][1]===12.0,'January canonical correct');
    check($connection->canonical['2026 | GB 1 | Kategori A'][13]===20.0,'full year correct');
    check($connection->canonical['2026 | GB 1 | Kategori A'][14]===12.0,'YTD correct');
    $history=\loadTargetRevenueInputHistory($connection,'2026-10');
    check((float)$history[1]['A'][0]['months'][11]===8.0,'detail persisted for next month');
    check(\loadTargetRevenueInputHistory($connection,'2027-02')===[],'year isolation');
    check(\loadTargetRevenueInputHistory($connection,'2026-01')===[],'January resets');
    runUpload($source,$path);check(count($connection->canonical)===30,'reupload no duplicates');
    $before=[$connection->canonical,$connection->details];$connection->failDetail=true;
    $sheet->setCellValue('C8',90);$writer->save($path);
    $failed=runUpload($source,$path);check(!$failed['success'],'detail failure rejected');
    check([$connection->canonical,$connection->details]===$before,'detail and summaries roll back together');
    check($connection->rollbacks===1,'rollback executed');
    $connection->failDetail=false;
    $_FILES=[];$_SERVER['REQUEST_METHOD']='POST';
    $GLOBALS['syntheticRevenueJson']=json_encode(['year'=>2026,'month'=>9,'rows'=>[['code'=>'category1A','months'=>array_fill(0,12,4)]]]);
    ob_start();try{eval('namespace '.__NAMESPACE__.';use \Throwable;use \mysqli;use \RuntimeException;use \InvalidArgumentException;'.$source);$legacy=json_decode(ob_get_contents(),true);}finally{ob_end_clean();}
    check($legacy['success'],'legacy JSON upload still works');
    check($connection->details[2026]===null,'legacy replacement invalidates stale product details');
    check(count($connection->canonical)===1,'legacy replaces same year without duplicates');
}finally{unlink($path);$book->disconnectWorksheets();}
echo "PASS actual Target Revenue API with database double: canonical values, YTD, detail history, no duplicates, year isolation and atomic rollback.\n";
