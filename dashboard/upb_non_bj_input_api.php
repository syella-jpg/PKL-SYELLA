<?php
require_once __DIR__.'/../includes/database.php';require_once __DIR__.'/../includes/upb_non_bj_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: POST, OPTIONS');}
if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('Gunakan POST untuk upload UPB NON BJ.');
 $period=$_POST['period']??'';if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period))throw new InvalidArgumentException('Periode UPB NON BJ tidak valid.');
 $file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Upload file ODS UPB NON BJ gagal.');$bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new InvalidArgumentException('File tidak dapat dibaca.');validateUpbNonBjOds($bytes,$period);
 $conn=openReportConnection();ensureUpbNonBjInputStore($conn);
 $base64=base64_encode($bytes);$q=$conn->prepare('INSERT INTO `upb non bj` (report_period,base64) VALUES (?,?) ON DUPLICATE KEY UPDATE base64=VALUES(base64)');$q->bind_param('ss',$period,$base64);$q->execute();$q->close();echo json_encode(['success'=>true,'period'=>$period]);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}catch(Throwable $e){error_log('UPB NON BJ input: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'UPB NON BJ gagal disimpan. Periksa koneksi database.']);}finally{if($conn)$conn->close();}
