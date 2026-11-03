<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/unit_sell_out_targets.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}
$conn=null;$book=null;
try{
 if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Metode tidak didukung.');
 $period=$method==='POST'?($_POST['period']??''):($_GET['period']??'');
 if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$period))throw new InvalidArgumentException('Periode TARGET UNIT SELL OUT tidak valid.');
 $year=(int)substr($period,0,4);
 if($method==='POST'){
  $file=$_FILES['file']??null;
  if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('File belum berhasil diunggah.');
  if(!in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),['ods','xlsx'],true)||$file['size']>8*1024*1024)throw new InvalidArgumentException('Gunakan file ODS/XLSX maksimal 8 MB.');
  $book=readUnitSellOutTargetBook($file['tmp_name']);$records=parseUnitSellOutTargets($book,$year);
 }
 $conn=openReportConnection();ensureUnitSellOutTargets($conn);
 if($method==='POST'){
  $products=array_values(array_unique(array_column($records,'product')));
  $conn->begin_transaction();
  $q=$conn->prepare('DELETE FROM `target unit sell out` WHERE report_year=? AND product=?');
  foreach($products as $product){$q->bind_param('is',$year,$product);$q->execute();}$q->close();
  $q=$conn->prepare('INSERT INTO `target unit sell out` (report_year,product,kind,month,units) VALUES (?,?,?,?,?)');
  foreach($records as $row){$q->bind_param('issis',$year,$row['product'],$row['kind'],$row['month'],$row['units']);$q->execute();}$q->close();$conn->commit();
  echo json_encode(['success'=>true,'year'=>$year,'groups'=>$products,'count'=>count($records)]);
 }else{
  $q=$conn->prepare('SELECT report_year,product,kind,month,units FROM `target unit sell out` WHERE report_year=? ORDER BY product,kind,month');$q->bind_param('i',$year);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();echo json_encode(['success'=>true,'data'=>$rows]);
 }
}catch(Throwable $e){if($conn){try{$conn->rollback();}catch(Throwable $ignored){}}error_log('TARGET UNIT SELL OUT: '.$e->getMessage());http_response_code($e instanceof InvalidArgumentException?400:500);echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Target UNIT SELL OUT gagal diproses. Periksa koneksi database dan log server.']);}
finally{if($book)$book->disconnectWorksheets();if($conn)$conn->close();}
