<?php
require_once __DIR__.'/../includes/upload_file_locks.php';
guardReportFileUpload('launch_input_api');
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/launch_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
    if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Gunakan GET atau POST untuk LAUNCH.');
    $source=$method==='POST'?$_POST:$_GET;$period=$source['period']??'';if(!is_string($period))throw new InvalidArgumentException('Periode LAUNCH tidak valid.');launchInputPeriod($period);
    $conn=openReportConnection();
    if($method==='POST'){$file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Pilih file ODS LAUNCH.');$bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new InvalidArgumentException('File LAUNCH tidak dapat dibaca.');saveLaunchInput($conn,$period,$bytes,$file['name']);echo json_encode(['success'=>true,'section'=>'LAUNCH','period'=>$period]);}
    else{
        if(($_GET['exact']??'')==='1'){
            $record=findLaunchInput($conn,$period);
            if(!$record){echo json_encode(['success'=>true,'base64'=>null]);return;}
            $bytes=base64_decode($record['base64'],true);
            if($bytes===false)throw new RuntimeException('File LAUNCH tersimpan tidak valid.');
        }else $bytes=loadLaunchInputOds($conn,$period);
        echo json_encode(['success'=>true,'base64'=>base64_encode($bytes)]);
    }
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('LAUNCH input: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'File LAUNCH gagal diproses. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
