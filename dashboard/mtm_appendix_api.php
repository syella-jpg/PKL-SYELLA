<?php
require_once __DIR__.'/../includes/database.php';require_once __DIR__.'/../includes/mtm_appendix_store.php';require_once __DIR__.'/../vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://localhost:8000','http://127.0.0.1:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;$book=null;
try{
 if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Metode tidak didukung.');
 $period=$method==='POST'?($_POST['period']??''):($_GET['period']??'');if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$period))throw new InvalidArgumentException('Periode lampiran MTM tidak valid.');$year=(int)substr($period,0,4);
 if($method==='POST'){
  $file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('File belum berhasil diunggah.');
  if(!in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),['ods','xlsx'],true)||$file['size']>8*1024*1024)throw new InvalidArgumentException('Gunakan file lampiran ODS/XLSX maksimal 8 MB.');
  $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);if(!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods)&&!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx))throw new InvalidArgumentException('Isi file harus ODS atau XLSX.');
  $names=$reader->listWorksheetNames($file['tmp_name']);$reader->setLoadSheetsOnly($names[0]);$reader->setReadDataOnly(true);$reader->setReadEmptyCells(false);
  $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter{public function readCell($columnAddress,$row,$worksheetName=''):bool{return $row<=12&&\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress)<=30;}});
  $book=$reader->load($file['tmp_name']);$sheet=$book->getSheet(0);$label=trim((string)$sheet->getCell('A1')->getValue());
  if(!preg_match('/^(?:Y|FY\s*|TAHUN\s*)?(20\d{2})$/i',$label,$match)||(int)$match[1]!==$year)throw new InvalidArgumentException('Tahun awal tabel lampiran MTM harus sesuai periode upload '.$year.'.');
  $headers=implode(' ',array_map(fn($row)=>implode(' ',array_filter($row,'is_string')),$sheet->rangeToArray('A1:AD4',null,false,false)));
  if(stripos($headers,'Budget Spending')===false||!preg_match('/\bMTM\b/i',$headers))throw new InvalidArgumentException('Tabel lampiran MTM / Budget Spending tidak dikenali.');
  $bytes=file_get_contents($file['tmp_name']);$name=basename($file['name']);$sha=hash('sha256',$bytes);
 }
 $conn=openReportConnection();ensureMtmAppendixStore($conn);
 if($method==='POST'){
  $anchor=$year.'-01';
  $q=$conn->prepare("INSERT INTO mtm_monthly_values (report_period,metric_code,value_type,month_number,amount,appendix_name,appendix_data,appendix_sha256,appendix_updated_at) VALUES (?,'APPENDIX','FILE',0,NULL,?,?,?,NOW()) ON DUPLICATE KEY UPDATE appendix_name=VALUES(appendix_name),appendix_data=VALUES(appendix_data),appendix_sha256=VALUES(appendix_sha256),appendix_updated_at=VALUES(appendix_updated_at)");$blob=null;$q->bind_param('ssbs',$anchor,$name,$blob,$sha);for($i=0;$i<strlen($bytes);$i+=262144)$q->send_long_data(2,substr($bytes,$i,262144));$q->execute();$q->close();echo json_encode(['success'=>true,'year'=>$year]);
 }else{
  $first=($year-1).'-01';$last=$year.'-12';$q=$conn->prepare("SELECT report_period,appendix_name,appendix_data,appendix_sha256 FROM mtm_monthly_values WHERE metric_code='APPENDIX' AND value_type='FILE' AND month_number=0 AND report_period BETWEEN ? AND ? ORDER BY report_period");$q->bind_param('ss',$first,$last);$q->execute();$rows=[];foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $row){$rows[]=['year'=>(int)substr($row['report_period'],0,4),'name'=>$row['appendix_name'],'base64'=>base64_encode($row['appendix_data']),'sha256'=>$row['appendix_sha256']];}$q->close();echo json_encode(['success'=>true,'data'=>$rows]);
 }
}catch(Throwable $e){error_log('MTM appendix: '.$e->getMessage());http_response_code($e instanceof InvalidArgumentException?400:500);echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Lampiran MTM gagal diproses. Periksa database dan log server.']);}
finally{if($book)$book->disconnectWorksheets();if($conn)$conn->close();}
