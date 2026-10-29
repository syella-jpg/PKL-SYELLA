<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/tm_bod_analysis_map.php';
require_once __DIR__.'/../includes/tm_bod_annual.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://localhost:8000','http://127.0.0.1:8000'],true)){
 header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Headers: Content-Type, Authorization');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}
$conn=null;
try {
 if(!in_array($method,['GET','POST'],true)){http_response_code(405);throw new InvalidArgumentException('Metode tidak didukung.');}
 $conn=openReportConnection();$conn->query("SET time_zone='+07:00'");
 $conn->query("CREATE TABLE IF NOT EXISTS tm_bod_transfer_sessions (token_hash CHAR(64) PRIMARY KEY, username VARCHAR(150) NOT NULL, expires_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $conn->query("CREATE TABLE IF NOT EXISTS tm_bod_analysis_transfers (
    source_note_id VARCHAR(64) NOT NULL, report_period CHAR(7) NOT NULL, destination VARCHAR(150) NOT NULL,
    analysis_text MEDIUMTEXT NOT NULL, sent_by VARCHAR(150) NOT NULL, sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(source_note_id,report_period), INDEX idx_tm_bod_transfer_period(report_period)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 ensureTmBodAnnual($conn);
 $column=$conn->query("SHOW COLUMNS FROM tm_bod_analysis_transfers LIKE 'destination_ids'");
 if($column->num_rows===0){try{$conn->query('ALTER TABLE tm_bod_analysis_transfers ADD COLUMN destination_ids MEDIUMTEXT NULL');}catch(mysqli_sql_exception $e){if((int)$e->getCode()!==1060)throw $e;}}
 $column->free();
 if($method==='GET') {
  $year=$_GET['year']??'';$quarter=$_GET['quarter']??'';
  if(!preg_match('/^20\d{2}$/',$year)||!preg_match('/^Q[1-4]$/',$quarter))throw new InvalidArgumentException('Tahun atau kuartal tidak valid.');
  $first=$year.'-'.str_pad(((int)$quarter[1]-1)*3+1,2,'0',STR_PAD_LEFT);$last=$year.'-'.str_pad((int)$quarter[1]*3,2,'0',STR_PAD_LEFT);
  $q=$conn->prepare('SELECT source_note_id,report_period,destination,destination_ids,analysis_text,sent_by,sent_at FROM tm_bod_analysis_transfers WHERE report_period BETWEEN ? AND ? ORDER BY sent_at,source_note_id');$q->bind_param('ss',$first,$last);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
  $original=json_decode(file_get_contents(__DIR__.'/assets/tm-bod/template-2026.json'),true);
  $norm=fn($v)=>strtoupper(preg_replace('/\s+/u',' ',trim($v)));
  foreach($rows as &$row){
    $ids=!empty($row['destination_ids'])?json_decode($row['destination_ids'],true):[];
    if(!$ids)foreach($original['slides'] as $slide)if(in_array($norm($slide['subtitle']?:$slide['title']),array_map($norm,explode('|',$row['destination'])),true))$ids[]=$slide['id'];
    $row['destination_ids']=$ids;
  }unset($row);
  echo json_encode(['success'=>true,'data'=>$rows]);exit;
 }
 $data=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
 if(($data['action']??'')==='login') {
  if(($data['username']??'')!=='admin 1'||($data['password']??'')!=='admin 1'){http_response_code(401);throw new InvalidArgumentException('Username atau password salah.');}
  $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
  $conn->query('DELETE FROM tm_bod_transfer_sessions WHERE expires_at<=NOW()');
  $q=$conn->prepare("INSERT INTO tm_bod_transfer_sessions (token_hash,username,expires_at) VALUES (?,'admin 1',DATE_ADD(NOW(),INTERVAL 1 DAY))");$q->bind_param('s',$hash);$q->execute();$q->close();echo json_encode(['success'=>true,'token'=>$token]);exit;
 }
 $authorization=$_SERVER['HTTP_AUTHORIZATION']??'';$token=preg_match('/^Bearer ([a-f0-9]{64})$/',$authorization,$match)?$match[1]:'';$hash=hash('sha256',$token);
 $q=$conn->prepare("SELECT username FROM tm_bod_transfer_sessions WHERE token_hash=? AND expires_at>NOW() AND username='admin 1'");$q->bind_param('s',$hash);$q->execute();$user=$q->get_result()->fetch_assoc();$q->close();
 if(!$user){http_response_code(403);throw new InvalidArgumentException('Hanya admin 1 yang dapat mengirim analisis ke TM BOD. Login ulang.');}
 $id=$data['id']??'';$period=$data['period']??'';
 if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/',$id)||!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$period))throw new InvalidArgumentException('Analisis atau periode tidak valid.');
 $q=$conn->prepare('SELECT report_name,tab_name,analysis_text FROM report_analysis WHERE id=?');$q->bind_param('s',$id);$q->execute();$note=$q->get_result()->fetch_assoc();$q->close();
 if(!$note){http_response_code(404);throw new InvalidArgumentException('Catatan tidak ditemukan. Muat ulang halaman.');}
 $template=tmBodActiveTemplate($conn,(int)substr($period,0,4));
 if(!$template){http_response_code(409);throw new InvalidArgumentException('Template TM BOD tahun ini belum aktif. Admin 1 perlu mengaktifkannya terlebih dahulu.');}
 $ids=$template['routes'][$note['report_name'].'|'.$note['tab_name']]??[];
 $byId=[];foreach($template['slides'] as $slide)$byId[$slide['id']]=$slide;
 if(!$ids)throw new InvalidArgumentException('Bagian ini belum mempunyai tujuan otomatis pada template tahun tersebut. Atur pemetaan template terlebih dahulu.');
 $names=[];foreach($ids as $target){if(empty($byId[$target]['enabled']))throw new InvalidArgumentException('Slide tujuan sudah dinonaktifkan. Perbarui pemetaan template.');$names[]=$byId[$target]['subtitle']?:$byId[$target]['title'];}
 $destination=implode(' dan ',$names);$destinationIds=json_encode($ids,JSON_THROW_ON_ERROR);
 $revision=hash('sha256',$note['analysis_text'].json_encode($template));
 if(($data['action']??'')==='preview'){echo json_encode(['success'=>true,'destination'=>str_replace('|',' dan ',$destination),'text'=>$note['analysis_text'],'revision'=>$revision]);exit;}
 if(($data['action']??'')!=='send')throw new InvalidArgumentException('Aksi tidak valid.');
 if(!is_string($data['revision']??null)||!hash_equals($revision,$data['revision'])){http_response_code(409);throw new InvalidArgumentException('Isi catatan berubah setelah konfirmasi dibuka. Coba kirim kembali.');}
 $q=$conn->prepare('INSERT INTO tm_bod_analysis_transfers (source_note_id,report_period,destination,analysis_text,sent_by,destination_ids) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE destination_ids=VALUES(destination_ids),destination=VALUES(destination),analysis_text=VALUES(analysis_text),sent_by=VALUES(sent_by),sent_at=CURRENT_TIMESTAMP');$shortDestination=mb_substr($destination,0,150);$q->bind_param('ssssss',$id,$period,$shortDestination,$note['analysis_text'],$user['username'],$destinationIds);$q->execute();$q->close();echo json_encode(['success'=>true,'destination'=>str_replace('|',' dan ',$destination)]);
}catch(Throwable $error){error_log('TM BOD analysis: '.$error->getMessage());if(http_response_code()<400)http_response_code($error instanceof InvalidArgumentException?400:500);echo json_encode(['success'=>false,'message'=>$error instanceof InvalidArgumentException?$error->getMessage():'Pengiriman TM BOD gagal. Periksa database dan log server.']);}
finally{if($conn)$conn->close();}
