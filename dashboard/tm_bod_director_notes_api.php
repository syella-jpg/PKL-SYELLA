<?php
require_once __DIR__.'/../includes/report_access.php';
enforceReportAccess('tm_bod_director_notes_api');
require_once __DIR__.'/../includes/database.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){
    header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{
    if(!in_array($method,['GET','POST'],true)){http_response_code(405);throw new InvalidArgumentException('Metode tidak didukung.');}
    $data=$method==='POST'?json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR):$_GET;
    $year=filter_var($data['year']??null,FILTER_VALIDATE_INT);$quarter=$data['quarter']??'';
    if(!$year||$year<2025||$year>2100||!is_string($quarter)||!preg_match('/^Q[1-4]$/D',$quarter))throw new InvalidArgumentException('Tahun atau kuartal tidak valid.');
    $authorization=$_SERVER['HTTP_AUTHORIZATION']??'';
    if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)){http_response_code(401);throw new InvalidArgumentException('Login ulang untuk membuka catatan direksi.');}
    $hash=hash('sha256',$m[1]);$conn=openReportConnection();$conn->query("SET time_zone='+07:00'");
    $q=$conn->prepare('SELECT username FROM tm_bod_transfer_sessions WHERE token_hash=? AND expires_at>NOW()');$q->bind_param('s',$hash);$q->execute();$user=$q->get_result()->fetch_assoc();$q->close();
    if(!$user||!in_array($user['username'],['direksi','admin 1'],true)){http_response_code(403);throw new InvalidArgumentException('Sesi tidak berlaku. Silakan login ulang.');}
    $conn->query("CREATE TABLE IF NOT EXISTS tm_bod_director_notes (id CHAR(32) NOT NULL PRIMARY KEY,report_year SMALLINT UNSIGNED NOT NULL,quarter CHAR(2) NOT NULL,note_text TEXT NOT NULL,created_by VARCHAR(150) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_director_period (report_year,quarter,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if($method==='POST'){
        if($user['username']!=='direksi'){http_response_code(403);throw new InvalidArgumentException('Catatan ini diisi oleh direksi.');}
        $text=$data['text']??null;if(!is_string($text)||trim($text)===''||mb_strlen($text)>10000)throw new InvalidArgumentException('Isi catatan maksimal 10.000 karakter.');
        $text=trim($text);$id=bin2hex(random_bytes(16));$q=$conn->prepare('INSERT INTO tm_bod_director_notes (id,report_year,quarter,note_text,created_by) VALUES (?,?,?,?,?)');$q->bind_param('sisss',$id,$year,$quarter,$text,$user['username']);$q->execute();$q->close();
        echo json_encode(['success'=>true,'id'=>$id]);
    }else{
        $q=$conn->prepare('SELECT id,note_text,created_by,created_at FROM tm_bod_director_notes WHERE report_year=? AND quarter=? ORDER BY created_at DESC,id DESC LIMIT 200');$q->bind_param('is',$year,$quarter);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();echo json_encode(['success'=>true,'data'=>$rows]);
    }
}catch(Throwable $e){error_log('Director notes: '.$e->getMessage());if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException?400:500);echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Catatan belum dapat diproses. Periksa koneksi database.']);}
finally{if($conn)$conn->close();}
