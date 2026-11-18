<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/bod_bi_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: POST, OPTIONS');}
if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('Gunakan POST untuk upload BOD.');$period=$_POST['period']??'';if(!is_string($period))throw new InvalidArgumentException('Periode tidak valid.');bodBiInputPeriod($period);
 $file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Upload ODS BOD gagal.');$bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new InvalidArgumentException('File tidak dapat dibaca.');
 $conn=openReportConnection();saveBodBiInput($conn,$period,$bytes);echo json_encode(['success'=>true,'section'=>BOD_BI_INPUT_SECTION,'period'=>$period]);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('BOD BI input: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Data BOD gagal disimpan. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
