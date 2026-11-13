<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/upb_distr_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, OPTIONS');}
if(($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
    $period=$_GET['period']??'';if(!is_string($period))throw new InvalidArgumentException('Periode tidak valid.');distrPeriod($period);
    $conn=openReportConnection();$record=findUpbDistrInput($conn,$period,'DATA PARETO UPB');$saved=$record?base64_decode($record['base64'],true):null;
    echo json_encode(['success'=>true,'base64'=>base64_encode(buildDataParetoUpbTemplateOds($period,$saved?:null))]);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('DATA PARETO UPB report: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Sheet DATA PARETO UPB gagal dimuat. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
