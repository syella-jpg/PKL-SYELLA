<?php
namespace IbAnnualReportTest;
require __DIR__.'/../includes/ib_template_history.php';
class Connection extends \mysqli {
 public array $records=[],$annual=[];
 public function __construct(){parent::__construct();}
 public function set_charset(string $charset):bool{return true;}
 #[\ReturnTypeWillChange] public function query($sql,$mode=MYSQLI_STORE_RESULT){return new class{public int $num_rows=1;};}
 #[\ReturnTypeWillChange] public function prepare($sql){return new Statement($this,$sql);}
 public function close():bool{return true;}
}
class Statement {
 public function __construct(private Connection $conn,private string $sql){}
 public function bind_param($types,&...$values){}
 public function execute(){return true;}
 public function close(){}
 public function get_result(){return new class(str_contains($this->sql,'target_mcm IS NOT NULL')?$this->conn->annual:$this->conn->records){public function __construct(private array $records){} public function fetch_all($mode){return $this->records;}public function fetch_assoc(){return ['usd_rate'=>16700];}};}
}
$conn=new Connection();
function openReportConnection(){global $conn;return $conn;}
function check($condition,$message){if(!$condition)throw new \RuntimeException($message);}
foreach(range(1,12) as $month) {
 $row=['report_period'=>'2026-11','month_number'=>$month];
 foreach(['target_ib_fm','actual_ib_fm','target_ib_fd','actual_ib_fd','target_new_ib_fm','actual_new_ib_fm','target_new_ib_fd','actual_new_ib_fd','target_mcm','actual_mcm'] as $key)$row[$key]=10;
 $conn->records[]=$row;
}
$legacy=\loadIbPeriodHistory($conn,'2026-09');check(count($legacy)===9,'raw template history keeps earlier period behavior');
$annual=\loadIbPeriodHistory($conn,'2026-09',true);
check(count($annual)===12 && $annual[11]['target_mcm']===10,'report retains annual target');
check($annual[8]['actual_mcm']===10 && $annual[9]['actual_mcm']===null,'report actual stops at selected month');
$source=file_get_contents(__DIR__.'/../dashboard/revenue_ibfm_api.php');$source=preg_replace('/^<\?php\s*/','',$source);
$source=str_replace("require_once __DIR__ . '/../includes/database.php';",'',$source);
$_SERVER['REQUEST_METHOD']='GET';$_GET=['year'=>'2026','month'=>9,'period'=>'2026-09'];
ob_start();try{eval('namespace '.__NAMESPACE__.';use \\Throwable;use \\RuntimeException;'.$source);$payload=json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);}finally{ob_end_clean();}
check($payload['success'],'API succeeds with database double');$data=$payload['data'];
check(count($data['mcmTarget'])===12 && $data['mcmTarget'][11]==10,'December target included');
check($data['ytd']['mcmTarget']==90 && $data['fullYear']['mcmTarget']==120,'YTD and annual target differ correctly');
check($data['mcmActual'][9]===null && $data['fullYear']['mcmActual']==90,'actual annual total uses available selected months');
$conn->records[0]['target_mcm']=0;
check(\loadIbPeriodHistory($conn,'2026-09',true)[0]['target_mcm']===0,'explicit zero preserved');
$conn->records[0]['report_period']='2027-11';
check(\loadIbPeriodHistory($conn,'2027-09',true)[11]['target_mcm']===10,'period year not hardcoded');
// September selects an August snapshot; November holds the missing annual targets.
$conn->records=[];$conn->annual=[];
foreach(range(1,12) as $month) {
 $row=['report_period'=>'2026-08','month_number'=>$month];
 foreach(['target_ib_fm','actual_ib_fm','target_ib_fd','actual_ib_fd','target_new_ib_fm','actual_new_ib_fm','target_new_ib_fd','actual_new_ib_fd','target_mcm','actual_mcm'] as $key)$row[$key]=$month<=8?5:null;
 if($month===1)$row['target_mcm']=0;
 $conn->records[]=$row;
 $conn->annual[]=['report_period'=>'2026-11','month_number'=>$month,'target_mcm'=>10];
}
$merged=\loadIbPeriodHistory($conn,'2026-09',true);
check($merged[0]['target_mcm']===0,'known historical zero not replaced');
check($merged[7]['target_mcm']===5,'existing historical target retained');
check($merged[8]['target_mcm']===10 && $merged[11]['target_mcm']===10,'missing September-December targets filled from later annual upload');
check($merged[8]['actual_mcm']===null && $merged[11]['actual_mcm']===null,'target fallback never invents or imports later actuals');
check($merged[0]['report_period']==='2026-08','historical actual snapshot unchanged');
check(count(\loadIbPeriodHistory($conn,'2026-09'))===12,'editable input history policy unchanged');
echo "PASS IB annual export API: target Jan-Dec, actual through September, YTD/FY, zero, earlier template behavior (database double).\n";
