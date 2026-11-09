<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/bod_kx_sell_in_monthly.php';
require_once __DIR__.'/../includes/bod_kx_sell_in_targets.php';
require_once __DIR__.'/../includes/bod_kx_sell_in_input_store.php';
require_once __DIR__.'/../includes/bod_kx_sell_in_rod_input.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}
$conn=null;$book=null;$combined=null;
try{
 if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Metode tidak didukung.');
 $period=$method==='POST'?($_POST['period']??''):($_GET['period']??'');
 if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$period))throw new InvalidArgumentException('Periode BOD KX SELL IN tidak valid.');
 if($method==='POST'){
  $sourceType=$_POST['source_type']??'';
  if(!is_string($sourceType)||!in_array($sourceType,['','sales','rod'],true))throw new InvalidArgumentException('Pilih jenis Laporan Penjualan atau ROD.');
  $file=$_FILES['file']??null;
  if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('File belum berhasil diunggah.');
  if(!in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),['ods','xlsx'],true)||$file['size']>50*1024*1024)throw new InvalidArgumentException('Gunakan file ODS/XLSX maksimal 50 MB.');
  $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
  if(!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods)&&!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx))throw new InvalidArgumentException('Isi file harus workbook ODS atau XLSX.');
  $reader->setReadEmptyCells(false);
  // Source workbooks can contain formatting across thousands of otherwise empty cells.
  // Keep template merges/styles for its layout reader; raw sources need values/formulas only.
  $reader->setReadDataOnly(!in_array('_TEMPLATE',$reader->listWorksheetNames($file['tmp_name']),true));
  $book=$reader->load($file['tmp_name']);
  $year=(int)substr($period,0,4);$targetSheet=$book->getSheetByName((string)$year);
  if(isBodKxInputTemplate($book)){
   if(($_POST['monthly_template']??'')==='1')throw new InvalidArgumentException('Pilih file laporan penjualan bulanan asli. Template gabungan diunggah melalui File Dokumen.');
   $combined=parseBodKxInputTemplate($book,$period);
  }
  else {
   $rodSheets=[];foreach($book->getAllSheets() as $candidate)if(isBodKxRodSheet($candidate))$rodSheets[]=$candidate;$isTarget=(bool)$rodSheets;
   if($isTarget){
    if($sourceType==='sales')throw new InvalidArgumentException('File ini adalah ROD. Pilih jenis ROD lalu unggah kembali.');
    if(($_POST['monthly_template']??'')==='1')throw new InvalidArgumentException('Pilih jenis ROD lalu unggah file melalui File Dokumen.');
    if(!$targetSheet||!isBodKxRodSheet($targetSheet))$targetSheet=count($rodSheets)===1?$rodSheets[0]:null;
    if(!$targetSheet)throw new InvalidArgumentException('Pilih sheet ROD dengan nama tahun '.$year.' agar sumber tahun tidak tertukar.');
    $combined=parseBodKxRodInput($targetSheet,$year);
   }else{
    if($sourceType==='rod')throw new InvalidArgumentException('File ini bukan ROD. Pilih jenis Laporan Penjualan untuk laporan penjualan bulanan.');
    $sheet=$book->getSheetByName('MONTHLY_SALES_REPORT')??$book->getSheet(0);
    $records=parseBodKxSellInMonthly($sheet,$period);
    if($sourceType==='sales'||($_POST['monthly_template']??'')==='1')$combined=bodKxMonthlyInput($records,$period);
   }
  }
 }
 $conn=openReportConnection();
 $conn->set_charset('utf8mb4');
 $conn->query('CREATE TABLE IF NOT EXISTS bod_kx_sell_in_monthly (report_period CHAR(7) NOT NULL,product VARCHAR(30) NOT NULL,units DECIMAL(24,6) NULL,rupiah DECIMAL(24,6) NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(report_period,product)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 ensureBodKxTargets($conn);
 if($method==='POST'&&$combined===null)ensureBodKxInputStore($conn);
 if($method==='POST'&&$combined!==null){
  echo json_encode(saveBodKxCombinedInput($conn,$period,$combined));
 }elseif($method==='POST'&&$isTarget){
  $conn->begin_transaction();invalidateBodKxInputState($conn,$period,true);$q=$conn->prepare('DELETE FROM `BOD KX SELL IN target` WHERE report_year=?');$q->bind_param('i',$year);$q->execute();$q->close();
  $q=$conn->prepare('INSERT INTO `BOD KX SELL IN target` (report_year,month,product,rupiah,units) VALUES (?,?,?,?,?)');
  foreach($records as $record){$q->bind_param('iisss',$year,$record['month'],$record['product'],$record['rupiah'],$record['units']);$q->execute();}$q->close();$conn->commit();
  echo json_encode(['success'=>true,'kind'=>'target','year'=>$year,'count'=>count($records)]);
 }elseif($method==='POST'){
  $conn->begin_transaction();invalidateBodKxInputState($conn,$period,false);$q=$conn->prepare('DELETE FROM bod_kx_sell_in_monthly WHERE report_period=?');$q->bind_param('s',$period);$q->execute();$q->close();
  $q=$conn->prepare('INSERT INTO bod_kx_sell_in_monthly (report_period,product,units,rupiah) VALUES (?,?,?,?)');
  foreach($records as $record){$q->bind_param('ssss',$period,$record['product'],$record['units'],$record['rupiah']);$q->execute();}$q->close();$conn->commit();
  echo json_encode(['success'=>true,'period'=>$period,'count'=>count($records)]);
 }else{
  $year=(int)substr($period,0,4);$first=($year-1).'-01';$last=$year.'-12';
  $q=$conn->prepare('SELECT report_period,product,units,rupiah FROM bod_kx_sell_in_monthly WHERE report_period BETWEEN ? AND ? ORDER BY report_period,product');$q->bind_param('ss',$first,$last);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
  $prior=$year-1;$q=$conn->prepare('SELECT report_year,month,product,rupiah,units FROM `BOD KX SELL IN target` WHERE report_year BETWEEN ? AND ? ORDER BY report_year,month,product');$q->bind_param('ii',$prior,$year);$q->execute();$targets=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();echo json_encode(['success'=>true,'data'=>$rows,'targets'=>$targets]);
 }
}catch(Throwable $e){if($conn){try{$conn->rollback();}catch(Throwable $ignored){}}error_log('BOD KX SELL IN monthly: '.$e->getMessage());http_response_code($e instanceof InvalidArgumentException?400:500);echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Sub Total BOD KX SELL IN gagal disimpan. Periksa koneksi database dan log server.']);}
finally{if($book)$book->disconnectWorksheets();if($conn)$conn->close();}
