<?php
require_once __DIR__.'/../includes/report_access.php';
enforceReportAccess('service_level_input_api');
require_once __DIR__.'/../includes/upload_file_locks.php';
guardReportFileUpload('service_level_input_api');
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/service_level_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
    if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Gunakan GET atau POST.');
    $input=$method==='POST'?$_POST:$_GET;$period=$input['period']??'';if(!is_string($period))throw new InvalidArgumentException('Periode tidak valid.');serviceLevelPeriod($period);
    $conn=openReportConnection();
    if($method==='POST'){
        $file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Pilih file ODS SERVICE LEVEL maksimal 50 MB.');
        $bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new InvalidArgumentException('File tidak dapat dibaca.');
        saveServiceLevelInput($conn,$period,$bytes,$file['name']);echo json_encode(['success'=>true,'section'=>'SERVICE LEVEL','period'=>$period]);
    }else echo json_encode(['success'=>true,'base64'=>base64_encode(loadServiceLevelInputOds($conn,$period))]);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('SERVICE LEVEL input: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'SERVICE LEVEL gagal diproses. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
