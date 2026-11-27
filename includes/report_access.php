<?php
/** Shared, server-enforced Pharma scope. No workbook or database contents logged. */
function reportIsPharma(): bool {return ($GLOBALS['reportAccessUser']??'')==='pharma';}
function reportInputGbs(): array {return reportIsPharma()?[1,4]:range(1,6);}
function reportPharmaReports(): array {return [
 'Revenue Konimex Selling Out (RP)','MCM Konimex Sell In (RP)','Revenue Konimex Selling In (RP)','Ratio Biaya (Biaya vs Selling in)',
 'RATIO SELL OUT VS SELL IN (RUPIAH)','RATIO SELL OUT VS SELL IN (UNIT)','MTM Contribution','Revenue Konimex sell in (Unit)',
 'Revenue Konimex Sell Out (UNIT)','Achievement & Growth Sell Out & Sell In (RP & UNIT)'];}
function reportPharmaTab(string $report,string $tab): bool {return in_array(strtoupper($report),array_map('strtoupper',reportPharmaReports()),true)&&in_array(strtoupper(trim($tab)),['PHARMA','FARMASI'],true);}
function reportPharmaUploads(): array {return ['UNIT & Revenue','REVENUE VERSI EDP MNJ','BIAYA','TARGET REVENUE','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target','BOD KX Sell In'];}
function reportGb($value): ?int {
 $value=strtoupper(trim((string)$value));
 if(preg_match('/^(?:GB\s*)?([1-6])$/D',$value,$m)||preg_match('/^(?:EXISTING|NEW|GB|EXIST GB |NP GB |CATEGORY|NONTARGET)([1-6])(?:[ABCD])?$/D',$value,$m))return (int)$m[1];
 return null;
}
function reportPharmaRows(array $rows,string $field,bool $reject=false): array {
 $allowed=[];foreach($rows as $row){$gb=reportGb($row[$field]??'');if(in_array($gb,[1,4],true))$allowed[]=$row;elseif($reject)throw new InvalidArgumentException('Akun pharma hanya boleh mengunggah GB 1 dan GB 4.');}
 return $allowed;
}
function reportPharmaFilter(string $endpoint,array $payload): array {
 if(($payload['success']??true)===false)return $payload;
 if($endpoint==='analysis_api'){
  $notes=[];foreach($payload['data']??[] as $key=>$items){$parts=explode('|',$key);if(count($parts)===3&&reportPharmaTab($parts[0],$parts[1]))$notes[$key]=$items;}$payload['data']=(object)$notes;return $payload;
 }
 if($endpoint==='biaya_api'){
  foreach(['target','actual'] as $kind)if(isset($payload['data'][$kind]))$payload['data'][$kind]=array_intersect_key($payload['data'][$kind],array_flip(['existing1','new1','existing4','new4']));return $payload;
 }
 if($endpoint==='revenue_so_targets_api'){
  if(isset($payload['data']['rows']))$payload['data']['rows']=reportPharmaRows($payload['data']['rows'],'code');return $payload;
 }
 $field=in_array($endpoint,['unit_sell_out_targets_api','revenue_edp_mnj_target_api','bod_kx_sell_in_api'],true)?'product':'gb';
 foreach(['data','targets'] as $key)if(isset($payload[$key])&&is_array($payload[$key])&&array_is_list($payload[$key]))$payload[$key]=reportPharmaRows($payload[$key],$field);
 if(isset($payload['total'],$payload['data'])&&is_array($payload['data']))$payload['total']=count($payload['data']);return $payload;
}
function enforceReportAccess(string $endpoint): void {
 $origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://localhost:8000','http://127.0.0.1:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Headers: Content-Type, Authorization');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
 if(($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS'){http_response_code(204);exit;}
 if(isset($GLOBALS['reportAccessUser']))return;
 $json=str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')?json_decode(file_get_contents('php://input'),true):[];
 $data=array_merge($_GET,is_array($json)?$json:[],$_POST);
 if(in_array($endpoint,['upload_files_api','tm_bod_analysis_api'],true)&&($data['action']??'')==='login')return;
 try{
  require_once __DIR__.'/database.php';require_once __DIR__.'/upload_file_locks.php';
  $auth=$_SERVER['HTTP_AUTHORIZATION']??'';$token=preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$m)?$m[1]:(string)($data['upload_token']??$_COOKIE['konimex_report_session']??'');
  if($token==='')throw new RuntimeException('Silakan login untuk mengakses laporan.',401);
  $db=openReportConnection();try{
   try{$user=uploadSessionUser($db,$token);}catch(Throwable $error){
    if(!str_starts_with($endpoint,'tm_bod_'))throw $error;
    $hash=hash('sha256',$token);$q=$db->prepare('SELECT username FROM tm_bod_transfer_sessions WHERE token_hash=? AND expires_at>NOW()');$q->bind_param('s',$hash);$q->execute();$record=$q->get_result()->fetch_assoc();$q->close();if(!$record)throw $error;$user=$record['username'];
   }
  }finally{$db->close();}
  $GLOBALS['reportAccessUser']=$user;if($user!=='pharma')return;
  if($endpoint==='upload_files_api'&&($data['action']??'')==='logout')return;
  if($endpoint==='bod_mnj_api'&&($_SERVER['REQUEST_METHOD']??'GET')==='GET')return;
  $allowed=['upload','report_input_template','upload_file_detection_api','unit_api','revenue_so_api','revenue_edp_mnj_api','revenue_so_targets_api','revenue_edp_mnj_target_api','unit_sell_out_targets_api','biaya_api','bod_kx_sell_in_api','analysis_api','analysis_locks_api'];
  if(!in_array($endpoint,$allowed,true))throw new RuntimeException('Akun pharma tidak mempunyai akses ke bagian ini.',403);
  if(in_array($endpoint,['analysis_api','analysis_locks_api'],true)&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!reportPharmaTab((string)($data['report']??''),(string)($data['tab']??'')))throw new RuntimeException('Analisis akun pharma hanya untuk tab Pharma/Farmasi.',403);
  if($endpoint==='report_input_template'&&($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new RuntimeException('Template hanya dapat diunduh melalui GET.',403);
  if($endpoint==='report_input_template'&&!in_array($data['section']??'',reportPharmaUploads(),true))throw new RuntimeException('Template ini bukan bagian Pharma.',403);
  if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&in_array($endpoint,['upload','bod_kx_sell_in_api'],true)&&($data['download']??false))throw new RuntimeException('Unduhan lengkap tidak tersedia untuk pharma.',403);
  if($endpoint!=='report_input_template')ob_start(static function($body)use($endpoint){$payload=json_decode($body,true);return is_array($payload)?json_encode(reportPharmaFilter($endpoint,$payload),JSON_UNESCAPED_UNICODE):$body;});
 }catch(Throwable $e){http_response_code(in_array((int)$e->getCode(),[401,403],true)?(int)$e->getCode():401);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>in_array((int)$e->getCode(),[401,403],true)?$e->getMessage():'Sesi tidak dapat diperiksa. Silakan login ulang.']);exit;}
}
