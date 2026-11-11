<?php
require_once __DIR__.'/../vendor/autoload.php';require_once __DIR__.'/../includes/database.php';require_once __DIR__.'/../includes/mnj_input_template.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Headers: Content-Type');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;
try{if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Metode tidak didukung.');$data=$method==='POST'?json_decode(file_get_contents('php://input'),true):$_GET;$period=$data['period']??'';if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period))throw new InvalidArgumentException('Periode MNJ tidak valid.');
 if($method==='POST'){$base64=$data['base64']??'';if(!is_string($base64))throw new InvalidArgumentException('File MNJ tidak valid.');$b=readMnjInput($base64,$period);$b->disconnectWorksheets();}
 $conn=openReportConnection();
 if($method==='POST'){$conn->query('CREATE TABLE IF NOT EXISTS mnj_input_files (report_period CHAR(7) PRIMARY KEY, base64 LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');$q=$conn->prepare('INSERT INTO mnj_input_files (report_period,base64) VALUES (?,?) ON DUPLICATE KEY UPDATE base64=VALUES(base64)');$q->bind_param('ss',$period,$base64);$q->execute();$q->close();echo json_encode(['success'=>true]);}
 else echo json_encode(['success'=>true,'data'=>findMnjInput($conn,$period)]);
}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}catch(Throwable $e){error_log('MNJ: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'MNJ gagal diproses. Periksa koneksi database.']);}finally{if($conn)$conn->close();}
