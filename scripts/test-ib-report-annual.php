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
 $annualRow=['report_period'=>'2026-11','month_number'=>$month];
 foreach(['target_ib_fm','target_ib_fd','target_new_ib_fm','target_new_ib_fd','target_mcm'] as $field)$annualRow[$field]=10;
 $conn->annual[]=$annualRow;
}
$merged=\loadIbPeriodHistory($conn,'2026-09',true);
check($merged[0]['target_mcm']===0,'known historical zero not replaced');
check($merged[7]['target_mcm']===5,'existing historical target retained');
check($merged[8]['target_mcm']===10 && $merged[11]['target_mcm']===10,'missing September-December targets filled from later annual upload');
foreach(['target_ib_fm','target_ib_fd','target_new_ib_fm','target_new_ib_fd','target_mcm'] as $field){
 check($merged[7][$field]===5,'known historical target retained: '.$field);
 check($merged[8][$field]===10&&$merged[11][$field]===10,'all revenue/MCM annual target series restored through December: '.$field);
}
check($merged[8]['actual_mcm']===null && $merged[11]['actual_mcm']===null,'target fallback never invents or imports later actuals');
check($merged[0]['report_period']==='2026-08','historical actual snapshot unchanged');
check(count(\loadIbPeriodHistory($conn,'2026-09'))===12,'editable input history policy unchanged');
// Full MCM is not sufficient: revenue-only gaps still require the annual plan.
foreach($conn->records as &$row)$row['target_mcm']=10;unset($row);
$merged=\loadIbPeriodHistory($conn,'2026-09',true);
check($merged[11]['target_ib_fm']===10&&$merged[11]['target_ib_fd']===10,'revenue gaps restored even when MCM is already complete');
// Never replace a known zero, import future actuals, or use another year's targets.
$conn->records[9]['target_ib_fm']=0;
array_unshift($conn->annual,['report_period'=>'2027-12','month_number'=>11,'target_new_ib_fm'=>9999]);
$merged=\loadIbPeriodHistory($conn,'2026-09',true);
check($merged[9]['target_ib_fm']===0,'known zero revenue target remains zero');
check($merged[10]['target_new_ib_fm']===10,'other-year annual target excluded');
foreach(['actual_ib_fm','actual_ib_fd','actual_new_ib_fm','actual_new_ib_fd','actual_mcm'] as $field)check($merged[11][$field]===null,'future actual remains blank: '.$field);
// Targets available for January reports also extend to December.
$january=\loadIbPeriodHistory($conn,'2026-01',true);
check($january[11]['target_new_ib_fd']===10&&$january[1]['actual_ib_fd']===null,'January report retains annual targets with January-only actual');
// Real API response must expose all target series and correct Full Year sums.
$_GET=['year'=>'2026','month'=>9,'period'=>'2026-09'];
ob_start();try{eval('namespace '.__NAMESPACE__.';use \\Throwable;use \\RuntimeException;'.$source);$annualPayload=json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);}finally{ob_end_clean();}
check($annualPayload['success'],'partial-history API succeeds');
foreach(['target','targetFd','newTarget','newTargetFd','mcmTarget'] as $key){
 check($annualPayload['data'][$key][11]==10,'December target returned by API: '.$key);
 check($annualPayload['data']['fullYear'][$key]===array_sum($annualPayload['data'][$key]),'Full Year sums all twelve available target months: '.$key);
}
echo "PASS IB annual export API: target Jan-Dec, actual through September, YTD/FY, zero, earlier template behavior (database double).\n";
