<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/upload_file_locks.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');uploadGuardCors();
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$db=null;
try{
 if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Gunakan GET atau POST.');
 $data=$method==='POST'?json_decode(file_get_contents('php://input'),true):$_GET;if(!is_array($data))throw new InvalidArgumentException('Permintaan tidak valid.');
 $db=openReportConnection();uploadLockSchema($db);
 if($method==='POST'&&($data['action']??'')==='login'){
  $user=$data['username']??'';$password=$data['password']??'';
  if(!is_string($user)||!in_array($user,['admin 1','admin 2','test 1','direksi'],true)||!is_string($password)||!hash_equals($user,$password))throw new RuntimeException('Username atau password salah.',401);
  $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
  $q=uploadLockQuery($db,'INSERT INTO report_upload_sessions(token_hash,username,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))','ss',[$hash,$user]);$q->close();
  echo json_encode(['success'=>true,'token'=>$token]);exit;
 }
 // Session token travels in a header for GET to avoid leaking it in URLs/logs.
 $auth=$_SERVER['HTTP_AUTHORIZATION']??'';$token=preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$m)?$m[1]:(string)($data['upload_token']??'');
 $user=uploadSessionUser($db,$token);
 if(!uploadCanManage($user))throw new RuntimeException('Kelola File hanya tersedia untuk admin 2.',403);
 $period=$data['period']??'';if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period))throw new InvalidArgumentException('Pilih periode laporan yang valid.');
 foreach(uploadFileCatalog() as $section)uploadSeedLegacy($db,$section,$period);
 if($method==='POST'){
  if(!in_array($data['action']??'',['unlock','lock'],true)||!is_string($data['section']??null))throw new InvalidArgumentException('Aksi tidak valid.');
  if($data['action']==='lock')uploadLockRelock($db,uploadCanonicalSection($data['section']),$period,$user);
  else uploadLockUnlock($db,uploadCanonicalSection($data['section']),$period,$user);
 }
 foreach(uploadFileCatalog() as $section)uploadSeedLegacy($db,$section,$period);
 $q=uploadLockQuery($db,'SELECT section,has_upload,locked,filename,uploaded_by,uploaded_at,unlocked_by,unlocked_at,revision FROM report_upload_files WHERE report_period=?','s',[$period]);$stored=[];
 foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$stored[$row['section']]=$row;$q->close();
 $rows=[];foreach(uploadFileCatalog() as $section){$record=$stored[$section]??[];if(empty($record['has_upload'])&&!uploadIsAnnual($section)){ $effective=uploadEffectiveMetadata($db,$section,$period);if($effective)$record=array_merge($record,$effective);}$rows[]=array_merge(['section'=>$section,'filename'=>null,'uploaded_by'=>null,'uploaded_at'=>null,'unlocked_by'=>null,'unlocked_at'=>null,'revision'=>0,'annual'=>uploadIsAnnual($section)],$record,['uploaded'=>!empty($record['has_upload']),'locked'=>!empty($record['locked'])]);}
 echo json_encode(['success'=>true,'period'=>$period,'canUnlock'=>uploadCanUnlock($user),'timezone'=>'Asia/Jakarta','data'=>$rows]);
}catch(Throwable $error){$code=(int)$error->getCode();http_response_code(in_array($code,[401,403,423],true)?$code:($error instanceof InvalidArgumentException?400:500));echo json_encode(['success'=>false,'message'=>$error instanceof InvalidArgumentException||in_array($code,[401,403,423],true)?$error->getMessage():'Kelola File gagal diakses. Periksa migrasi dan koneksi database.']);}
finally{if($db)$db->close();}
