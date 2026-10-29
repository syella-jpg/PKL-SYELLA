<?php
require_once __DIR__.'/../includes/database.php';require_once __DIR__.'/../includes/tm_bod_annual.php';require_once __DIR__.'/../includes/tm_bod_design.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Headers: Content-Type, Authorization');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
 if(!in_array($method,['GET','POST'],true)){http_response_code(405);throw new InvalidArgumentException('Metode tidak didukung.');}
 $data=$method==='POST'?json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR):$_GET;
 $year=filter_var($data['year']??null,FILTER_VALIDATE_INT);if(!$year||$year<2026||$year>2100)throw new InvalidArgumentException('Tahun template harus 2026–2100.');
 $conn=openReportConnection();$conn->query("SET time_zone='+07:00'");ensureTmBodAnnual($conn);
 $action=$data['action']??'active';
 if($method==='GET'&&$action==='active'){$active=tmBodActiveTemplate($conn,$year);echo json_encode(['success'=>true,'data'=>$active,'backgrounds'=>tmBodBackgrounds($conn,$active,$year)]);exit;}
 requireTmBodAdmin($conn);
 if($method==='GET'&&$action==='draft'){
  $q=$conn->prepare('SELECT draft_json,version FROM tm_bod_annual_templates WHERE report_year=?');$q->bind_param('i',$year);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
  $from=(int)($data['from']??($year-1));$config=!empty($row['draft_json'])?json_decode($row['draft_json'],true):tmBodActiveTemplate($conn,$year);
  if(!$config){$config=tmBodActiveTemplate($conn,$from);if(!$config)throw new InvalidArgumentException('Template sumber '.$from.' belum aktif. Pilih tahun sumber lain.');}
  echo json_encode(['success'=>true,'data'=>$config,'version'=>(int)($row['version']??0)]);exit;
 }
 if($method!=='POST'||!in_array($action,['save','activate'],true))throw new InvalidArgumentException('Aksi template tidak valid.');
 $conn->begin_transaction();$conn->query('INSERT IGNORE INTO tm_bod_annual_templates (report_year) VALUES ('.(int)$year.')');
 $q=$conn->prepare('SELECT draft_json,version FROM tm_bod_annual_templates WHERE report_year=? FOR UPDATE');$q->bind_param('i',$year);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
 if((int)($data['version']??-1)!==(int)$row['version']){http_response_code(409);throw new InvalidArgumentException('Template diubah dari sesi lain. Muat draf terbaru.');}
 if($action==='save'){
  $config=validateTmBodTemplate($data['config']??null);tmBodBackgrounds($conn,$config,$year);$json=json_encode($config,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
  $q=$conn->prepare("UPDATE tm_bod_annual_templates SET draft_json=?,version=version+1,updated_by='admin 1',updated_at=NOW() WHERE report_year=?");$q->bind_param('si',$json,$year);$q->execute();$q->close();$version=(int)$row['version']+1;
 }else{
  if(!$row['draft_json'])throw new InvalidArgumentException('Simpan draf terlebih dahulu.');
  $config=validateTmBodTemplate(json_decode($row['draft_json'],true),true);tmBodBackgrounds($conn,$config,$year);$json=json_encode($config,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$version=(int)$row['version']+1;
  $q=$conn->prepare("INSERT INTO tm_bod_template_versions (report_year,version,template_json,activated_by) VALUES (?,?,?,'admin 1')");$q->bind_param('iis',$year,$version,$json);$q->execute();$q->close();
  $q=$conn->prepare("UPDATE tm_bod_annual_templates SET active_json=?,version=?,updated_by='admin 1',updated_at=NOW() WHERE report_year=?");$q->bind_param('sii',$json,$version,$year);$q->execute();$q->close();
 }
 $conn->commit();echo json_encode(['success'=>true,'version'=>$version]);
}catch(Throwable $e){if($conn){try{$conn->rollback();}catch(Throwable $ignored){}}error_log('TM BOD templates: '.$e->getMessage());if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException?400:500);echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Template gagal diproses. Periksa database dan log server.']);}finally{if($conn)$conn->close();}
