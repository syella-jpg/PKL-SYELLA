<?php
require_once __DIR__.'/../includes/upb_distr_report.php';
require_once __DIR__.'/../includes/upb_distr_input_store.php';
require_once __DIR__.'/../includes/database.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, OPTIONS');}
if(($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS'){http_response_code(204);exit;}
$conn=null;
try{
    $period=$_GET['period']??'';if(!is_string($period))throw new InvalidArgumentException('Periode UPB DISTR tidak valid.');
    [$year]=distrPeriod($period);$conn=openReportConnection();
    $current=findUpbDistrInput($conn,$period);$prior=findUpbDistrInput($conn,($year-1).'-12');
    echo json_encode(['success'=>true,'base64'=>base64_encode(buildUpbDistrReportOds($period,$current?base64_decode($current['base64'],true):null,$prior?base64_decode($prior['base64'],true):null))]);
}catch(InvalidArgumentException $error){http_response_code(400);echo json_encode(['success'=>false,'message'=>$error->getMessage()]);}
catch(Throwable $error){error_log('UPB DISTR report: '.$error->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Template sheet UPB DISTR gagal dimuat.']);}
finally{if($conn)$conn->close();}
