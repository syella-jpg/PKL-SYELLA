<?php
require_once __DIR__.'/../includes/report_access.php';enforceReportAccess('template_settings_api');
require_once __DIR__.'/../includes/template_password.php';require_once __DIR__.'/../includes/database.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');$db=null;
try{
    if(($GLOBALS['reportAccessUser']??'')!=='admin 2')throw new RuntimeException('Pengaturan password sheet hanya tersedia untuk admin 2.',403);
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')throw new RuntimeException('Gunakan POST untuk menyimpan pengaturan.',405);
    $data=json_decode(file_get_contents('php://input'),true);
    if(!is_array($data)||!is_string($data['password']??null)||!is_string($data['confirmation']??null))throw new InvalidArgumentException('Isi password baru dan konfirmasinya.');
    if(!hash_equals($data['password'],$data['confirmation']))throw new InvalidArgumentException('Konfirmasi password tidak sama.');
    validateTemplatePassword($data['password']);$db=openReportConnection();saveTemplatePassword($db,$data['password'],'admin 2');
    echo json_encode(['success'=>true,'message'=>'Password sheet berhasil diubah. Gunakan password baru untuk template yang diunduh setelah perubahan ini.']);
}catch(Throwable $e){$code=(int)$e->getCode();http_response_code(in_array($code,[403,405],true)?$code:($e instanceof InvalidArgumentException?400:500));echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException||in_array($code,[403,405],true)?$e->getMessage():'Pengaturan belum berhasil disimpan. Periksa koneksi database.']);}
finally{if($db)$db->close();}
