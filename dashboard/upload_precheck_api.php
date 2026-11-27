<?php
require_once __DIR__.'/../includes/report_access.php';
enforceReportAccess('upload_precheck_api');
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/upload_precheck.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$db=null;
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);throw new InvalidArgumentException('Gunakan POST untuk pemeriksaan awal.');}
    if(!in_array($GLOBALS['reportAccessUser']??'',['admin 1','admin 2','test 1','pharma'],true)){http_response_code(403);throw new InvalidArgumentException('Akun ini tidak diizinkan memeriksa file upload.');}
    $section=$_POST['section']??'';$period=$_POST['period']??'';
    if(!is_string($section)||!is_string($period))throw new InvalidArgumentException('Bagian/periode tidak valid.');
    $section=uploadCanonicalSection($section);uploadValidateScope($section,$period);
    $file=$_FILES['file']??null;
    if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('File belum diterima untuk pemeriksaan awal.');
    if(!is_uploaded_file($file['tmp_name'])||filesize($file['tmp_name'])>50*1024*1024||!in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),['xlsx','ods'],true))throw new InvalidArgumentException('Gunakan file ODS/XLSX maksimal 50 MB.');
    $db=openReportConnection();
    // Defense in depth: this request may neither reserve upload locks nor save report rows.
    $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    uploadPrecheckInput($db,$section,$period,$file['tmp_name']);
    echo json_encode(['success'=>true,'checked'=>true,'message'=>'Pemeriksaan awal selesai. Belum ada data laporan yang disimpan.']);
}catch(InvalidArgumentException $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['success'=>false,'rejected'=>str_starts_with($e->getMessage(),'UPLOAD DITOLAK!'),'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Upload precheck failed: '.get_class($e));http_response_code(503);echo json_encode(['success'=>false,'rejected'=>false,'message'=>'Pemeriksaan awal belum dapat dilakukan. Periksa koneksi server, lalu coba kembali.']);}
finally{if($db){try{$db->rollback();}finally{$db->close();}}}
