<?php
require __DIR__.'/../includes/upload_file_locks.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
// Synthetic in-memory connection: never loads database.php, credentials or files.
class DummyResult {
    function __construct(public array $rows){}
    function fetch_assoc(){return $this->rows[0]??null;}
    function fetch_all($unused=null){return $this->rows;}
}
class DummyStatement {
    public array $values=[],$rows=[];
    function __construct(public DummyDb $db,public string $sql){}
    function bind_param($types,&...$values){$this->values=$values;}
    function execute(){
        $v=$this->values;$db=$this->db;$key=($v[0]??'').'|'.($v[1]??'');
        if(str_starts_with($this->sql,'INSERT IGNORE INTO report_upload_files'))$db->files[$key]??=['locked'=>0,'has_upload'=>0,'revision'=>0];
        elseif(str_starts_with($this->sql,'SELECT locked'))$this->rows=isset($db->files[$key])?[$db->files[$key]]:[];
        elseif(str_starts_with($this->sql,'UPDATE report_upload_files SET has_upload')){
            $key=$v[2].'|'.$v[3];$db->files[$key]=array_merge($db->files[$key],['locked'=>1,'has_upload'=>1,'filename'=>$v[0],'uploaded_by'=>$v[1],'revision'=>$db->files[$key]['revision']+1]);
        }elseif(str_starts_with($this->sql,'UPDATE report_upload_files SET locked=0')){$key=$v[1].'|'.$v[2];$db->files[$key]['locked']=0;$db->files[$key]['unlocked_by']=$v[0];}
        elseif(str_starts_with($this->sql,'UPDATE report_upload_files SET locked=1 WHERE')){$db->files[$key]['locked']=1;}
        elseif(str_starts_with($this->sql,'INSERT INTO report_upload_events'))$db->events[]=['sql'=>$this->sql,'values'=>$v];
        elseif(str_starts_with($this->sql,'SELECT username'))$this->rows=$v[0]===hash('sha256',str_repeat('a',64))?[['username'=>'admin 2']]:[];
        else throw new RuntimeException('Unexpected dummy SQL: '.$this->sql);
    }
    function get_result(){return new DummyResult($this->rows);}function close(){}
}
class DummyDb {
    public array $files=[],$events=[];public string $server_info='dummy-mysql';private $snapshot=null;
    function prepare($sql){return new DummyStatement($this,$sql);}function begin_transaction(){check($this->snapshot===null,'No overlapping transaction');$this->snapshot=[$this->files,$this->events];}
    function commit(){$this->snapshot=null;}function rollback(){if($this->snapshot!==null){[$this->files,$this->events]=$this->snapshot;$this->snapshot=null;}}
}
$db=new DummyDb();
check(uploadCanManage('admin 2')&&!uploadCanManage('test 1')&&!uploadCanManage('admin 1'),'Management visibility');check(uploadCanUnlock('admin 2')&&!uploadCanUnlock('test 1'),'Admin 2 only unlock');
check(uploadSessionUser($db,str_repeat('a',64))==='admin 2','Verified user');
try{uploadSessionUser($db,'spoof');throw new LogicException('Unauthorized token accepted');}catch(RuntimeException $e){check($e->getCode()===401,'No arbitrary username');}
check(uploadEndpointScope('upload',['divisi'=>'REVENUE VERSI EDP MNJ','period'=>'2026-06'])===['UNIT & Revenue','2026-06'],'Alias cannot bypass lock');
check(uploadEndpointScope('revenue_so_targets_api',['year'=>2027,'month'=>6])===['TARGET REVENUE','2027-06'],'Target period mapping');
try{uploadEndpointScope('upload',['divisi'=>'invented','period'=>'2026-06']);throw new LogicException('Unknown section accepted');}catch(InvalidArgumentException $e){}
try{uploadEndpointScope('revenue_so_targets_api',['period'=>'2026-06','year'=>2026,'month'=>7]);throw new LogicException('Different target scope accepted');}catch(InvalidArgumentException $e){}
// Failure leaves the slot available, success locks it and records the real actor.
uploadLockReserve($db,'DAFTAR','2026-06');uploadLockFinish($db,'DAFTAR','2026-06','test 1','dummy.ods',false);check(!$db->files['DAFTAR|2026-06']['locked'],'Failed upload is not locked');
uploadLockReserve($db,'DAFTAR','2026-06');uploadLockFinish($db,'DAFTAR','2026-06','test 1','dummy.ods',true);$row=$db->files['DAFTAR|2026-06'];check($row['locked']===1&&$row['uploaded_by']==='test 1'&&$row['revision']===1,'Successful upload records metadata and locks');
try{uploadLockReserve($db,'DAFTAR','2026-06');throw new LogicException('Second upload accepted');}catch(RuntimeException $e){check($e->getCode()===423,'Locked retry rejected');}
try{uploadLockUnlock($db,'DAFTAR','2026-06','test 1');throw new LogicException('test 1 unlocked');}catch(RuntimeException $e){check($e->getCode()===403,'Server enforces permission');}
uploadLockUnlock($db,'DAFTAR','2026-06','admin 2');check(!$db->files['DAFTAR|2026-06']['locked'],'Unlock works');
uploadLockRelock($db,'DAFTAR','2026-06','admin 2');check($db->files['DAFTAR|2026-06']['locked']===1,'Manual relock');
try{uploadLockRelock($db,'DAFTAR','2026-06','test 1');throw new LogicException('Viewer relocked');}catch(RuntimeException $e){check($e->getCode()===403,'Relock permission');}
uploadLockUnlock($db,'DAFTAR','2026-06','admin 2');
uploadLockReserve($db,'DAFTAR','2026-06');uploadLockFinish($db,'DAFTAR','2026-06','admin 1','replacement-dummy.ods',true);check($db->files['DAFTAR|2026-06']['revision']===2&&$db->files['DAFTAR|2026-06']['locked'],'Replacement re-locks');
uploadLockReserve($db,'DAFTAR','2026-07');uploadLockFinish($db,'DAFTAR','2026-07','admin 1','dummy.ods',true);uploadLockReserve($db,'LAUNCH','2026-06');uploadLockFinish($db,'LAUNCH','2026-06','admin 1','dummy.ods',true);
check(count($db->events)===7,'Upload/unlock audit trail retained');
$guard=file_get_contents(__DIR__.'/../includes/upload_file_locks.php');check(str_contains($guard,'FOR UPDATE')&&str_contains($guard,'BEGIN PESSIMISTIC'),'Database serialization for MySQL/TiDB');
$names=['daftar_input_api','launch_input_api','service_level_input_api','bod_jml_sdm_input_api','bod_bi_input_api','bod_kx_sell_in_api','bod_mnj_api','biaya_api','mnj_api','mtm_appendix_api','upb_non_bj_input_api','upb_bj_input_api','unit_sell_out_targets_api','revenue_so_targets_api','revenue_edp_mnj_target_api','upb_distr_input_api'];
foreach($names as $name){$code=file_get_contents(__DIR__.'/../dashboard/'.$name.'.php');check(strpos($code,"guardReportFileUpload('$name')")<strpos($code,'database.php'),'Guard before data writes: '.$name);}
echo "PASS dummy upload lifecycle, rollback, retry block, admin-only unlock, re-lock, isolated sections/months/years, verified sessions, audit and endpoint coverage. No live DB or spreadsheets accessed.\n";
