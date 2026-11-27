<?php
require_once __DIR__.'/../includes/report_access.php';
enforceReportAccess('near_ed_input_api');
require_once __DIR__.'/../includes/upload_file_locks.php';
guardReportFileUpload('near_ed_input_api');
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/near_ed_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
    if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Gunakan GET atau POST untuk NEAR ED.');
    $source=$method==='POST'?$_POST:$_GET;$period=$source['period']??'';if(!is_string($period))throw new InvalidArgumentException('Periode NEAR ED tidak valid.');nearEdPeriod($period);
    $conn=openReportConnection();
    if($method==='POST'){$file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Pilih file XLSX atau ODS NEAR ED.');$bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new InvalidArgumentException('File NEAR ED tidak dapat dibaca.');saveNearEdInput($conn,$period,$bytes,$file['name']);echo json_encode(['success'=>true,'section'=>'NEAR ED','period'=>$period]);}
    else{
        if(($_GET['exact']??'')==='1'){
            $record=findNearEdInput($conn,$period);
            if(!$record){echo json_encode(['success'=>true,'base64'=>null]);return;}
            $bytes=base64_decode($record['base64'],true);
            if($bytes===false)throw new RuntimeException('File NEAR ED tersimpan tidak valid.');
        }else {
            $record=findNearEdInput($conn,$period);$groups=[];
            if($record){$raw=base64_decode($record['base64'],true);if($raw===false)throw new RuntimeException('File NEAR ED tersimpan tidak valid.');$groups=readNearEdInput($raw);}
            $book=buildNearEdReport($period,$groups);$path=tempnam(sys_get_temp_dir(),'near-ed-report-');
            try{(new \PhpOffice\PhpSpreadsheet\Writer\Ods($book))->save($path);$bytes=file_get_contents($path);}finally{@unlink($path);$book->disconnectWorksheets();}
        }
        echo json_encode(['success'=>true,'base64'=>($bytes===null?null:base64_encode($bytes))]);
    }
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('NEAR ED input: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'File NEAR ED gagal diproses. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
