<?php
require_once __DIR__.'/../includes/report_access.php';
enforceReportAccess('daftar_input_api');
require_once __DIR__.'/../includes/upload_file_locks.php';
guardReportFileUpload('daftar_input_api');
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/daftar_input_store.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
$method=$_SERVER['REQUEST_METHOD']??'GET'; if($method==='OPTIONS'){http_response_code(204);exit;} $conn=null;
try {
    if (!in_array($method,['GET','POST'],true)) throw new InvalidArgumentException('Gunakan GET atau POST untuk DAFTAR.');
    $period=($method==='POST'?$_POST:$_GET)['period']??'';
    if(!is_string($period))throw new InvalidArgumentException('Periode DAFTAR tidak valid.'); daftarInputPeriod($period);
    if($method==='POST'){
        $file=$_FILES['file']??null;
        if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Upload DAFTAR gagal. Pilih file ODS.');
        if($file['size']>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
        $bytes=file_get_contents($file['tmp_name']); if($bytes===false)throw new InvalidArgumentException('File DAFTAR tidak dapat dibaca.');
        $conn=openReportConnection(); saveDaftarInput($conn,$period,$bytes,$file['name']);
        echo json_encode(['success'=>true,'section'=>'DAFTAR','period'=>$period]);
    }else{
        $conn=openReportConnection();
        if(($_GET['exact']??'')==='1'){
            $record=findDaftarInput($conn,$period);
            if(!$record||$record['report_period']!==$period){echo json_encode(['success'=>true,'base64'=>null]);return;}
            $bytes=base64_decode($record['base64'],true);
            if($bytes===false)throw new RuntimeException('File DAFTAR tersimpan tidak valid.');
        }else $bytes=loadDaftarInputTemplateOds($conn,$period);
        echo json_encode(['success'=>true,'base64'=>base64_encode($bytes)]);
    }
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('DAFTAR input: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Data DAFTAR gagal diproses. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
