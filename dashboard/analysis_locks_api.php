<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/analysis_locks.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://localhost:8000','http://127.0.0.1:8000'],true)){
 header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type');
}
if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}
$conn=null;
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);throw new InvalidArgumentException('Metode tidak didukung.');}
 $data=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
 $report=$data['report']??'';$tab=$data['tab']??'';$token=$data['token']??'';$name=$data['username']??'';$action=$data['action']??'';
 if(!is_string($report)||$report===''||strlen($report)>200||!is_string($tab)||$tab===''||strlen($tab)>120||!is_string($token)||!preg_match('/^[a-zA-Z0-9_-]{16,64}$/',$token)||!is_string($name)||strlen($name)>150||!in_array($action,['status','acquire','renew','release'],true))throw new InvalidArgumentException('Permintaan kunci analisis tidak valid.');
 if(in_array($action,['acquire','renew'],true)&&trim($name)==='')throw new InvalidArgumentException('Login ulang sebelum mengedit analisis.');
 $conn=openReportConnection();$conn->query("SET time_zone = '+07:00'");ensureAnalysisLockTable($conn);$scope=analysisLockScope($report,$tab);
 if($action==='release'){
  $q=$conn->prepare('DELETE FROM report_analysis_locks WHERE scope_id=? AND owner_token=?');$q->bind_param('ss',$scope,$token);$q->execute();$q->close();
 }elseif($action==='acquire'){
  $conn->begin_transaction();
  $q=$conn->prepare("INSERT IGNORE INTO report_analysis_locks (scope_id,owner_token,owner_name,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 60 SECOND))");$q->bind_param('sss',$scope,$token,$name);$q->execute();$q->close();
  $row=readAnalysisLock($conn,$scope,true);
  if(!$row['active']||hash_equals($row['owner_token'],$token)){
   $q=$conn->prepare('UPDATE report_analysis_locks SET owner_token=?,owner_name=?,expires_at=DATE_ADD(NOW(),INTERVAL 60 SECOND) WHERE scope_id=?');$q->bind_param('sss',$token,$name,$scope);$q->execute();$q->close();
  }
  $conn->commit();
 }elseif($action==='renew'){
  $q=$conn->prepare('UPDATE report_analysis_locks SET expires_at=DATE_ADD(NOW(),INTERVAL 60 SECOND) WHERE scope_id=? AND owner_token=? AND expires_at>NOW()');$q->bind_param('ss',$scope,$token);$q->execute();$q->close();
 }
 $row=readAnalysisLock($conn,$scope);$held=$row&&(bool)$row['active'];
 echo json_encode(['success'=>true,'held'=>$held,'mine'=>$held&&hash_equals($row['owner_token'],$token),'owner'=>$held?$row['owner_name']:null,'leaseSeconds'=>60]);
}catch(Throwable $error){
 if($conn){try{$conn->rollback();}catch(Throwable $ignored){}}
 error_log('Analysis locks: '.$error->getMessage());if(http_response_code()<400)http_response_code($error instanceof InvalidArgumentException?400:500);
 echo json_encode(['success'=>false,'message'=>$error instanceof InvalidArgumentException?$error->getMessage():'Kunci analisis gagal diakses. Periksa koneksi database dan izin tabel report_analysis_locks.']);
}finally{if($conn)$conn->close();}
