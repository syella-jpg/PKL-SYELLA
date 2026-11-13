<?php
require_once __DIR__.'/../includes/report_artifact_cache.php';
require_once __DIR__.'/../includes/database.php';require_once __DIR__.'/../includes/upb_non_bj_report.php';require_once __DIR__.'/../includes/upb_non_bj_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, OPTIONS');}
if(($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{$period=$_GET['period']??'';if(!is_string($period)||!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$match))throw new InvalidArgumentException('Periode UPB NON BJ tidak valid.');$conn=openReportConnection();$current=findUpbNonBjTemplate($conn,$period);$prior=findUpbNonBjTemplate($conn,((int)$match[1]-1).'-12');$currentBytes=$current?base64_decode($current['base64'],true):null;$priorBytes=$prior?base64_decode($prior['base64'],true):null;
$key=reportArtifactKey([$period,$current['base64']??'',$prior['base64']??''],array_map(static fn($file)=>__DIR__.'/../includes/'.$file,['report_artifact_cache.php','upb_non_bj_report.php','upb_non_bj_input_template.php','upb_bj_template_history.php','ods_report_styles.php']));
$key=reportArtifactKey([$key],[__DIR__.'/assets/input-templates/upb-non-bj.ods']);
$bytes=cachedReportArtifact('upb-non-bj-report',$key,static fn()=>buildUpbNonBjReportOds($period,$currentBytes,$priorBytes));echo json_encode(['success'=>true,'base64'=>base64_encode($bytes)]);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}catch(Throwable $e){error_log('UPB NON BJ report: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Data laporan UPB NON BJ gagal dimuat dari database.']);}finally{if($conn)$conn->close();}
