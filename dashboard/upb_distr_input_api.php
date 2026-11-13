<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/upb_distr_input_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: POST, OPTIONS');}
if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('Gunakan POST untuk upload UPB distributor.');
    $period=$_POST['period']??'';$section=$_POST['section']??'';
    if(!is_string($period)||!is_string($section))throw new InvalidArgumentException('Bagian atau periode tidak valid.');
    $table=distrStoreTable($section);$file=$_FILES['file']??null;
    if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Upload file ODS gagal.');
    $bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new InvalidArgumentException('File tidak dapat dibaca.');validateUpbDistrInput($bytes,$period,$section);
    if($section==='UPB DISTR'){$bytes=prepareUpbDistrInputOds($bytes,$period);validateUpbDistrInput($bytes,$period,$section);}
    $conn=openReportConnection();ensureUpbDistrInputStore($conn);$base64=base64_encode($bytes);
    $q=$conn->prepare("INSERT INTO `$table` (report_period,base64) VALUES (?,?) ON DUPLICATE KEY UPDATE base64=VALUES(base64)");$q->bind_param('ss',$period,$base64);$q->execute();$q->close();
    echo json_encode(['success'=>true,'period'=>$period,'section'=>$section]);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('UPB distributor input: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Data gagal disimpan. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
