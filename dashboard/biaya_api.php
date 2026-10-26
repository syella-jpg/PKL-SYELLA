<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/biaya_workbook.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://localhost:8000','http://127.0.0.1:8000'],true)) {
    header('Access-Control-Allow-Origin: '.$origin); header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type');
    if(($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK']??'')==='true') header('Access-Control-Allow-Private-Network: true');
}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='OPTIONS') { http_response_code(204); exit; }
$conn=null;
try {
    $period=$_POST['period']??$_GET['period']??'';
    if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$period)||(int)substr($period,0,4)<2025) throw new InvalidArgumentException('Periode biaya tidak valid.');
    if(!in_array($method,['GET','POST'],true)) { http_response_code(405); throw new InvalidArgumentException('Metode tidak didukung.'); }
    if($method==='POST') {
        $file=$_FILES['file']??[];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>50*1024*1024) throw new InvalidArgumentException('File biaya gagal diterima atau terlalu besar.');
        $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
        if(!$reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods && !$reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx) throw new InvalidArgumentException('Gunakan file ODS atau XLSX.');
        $book=$reader->load($file['tmp_name']); $rows=parseBiayaProducts($book,(int)substr($period,0,4)); $book->disconnectWorksheets();
    }
    $conn=openReportConnection();
    if($method==='POST') {
        saveBiayaProducts($conn,$period,$rows);
        $data=findBiayaRecord($conn,$period);
    } else $data=findBiayaRecord($conn,$period);
    echo json_encode(['success'=>true,'data'=>$data]);
} catch(Throwable $e) {
    error_log('BIAYA upload/read failed ['.get_class($e).']: '.$e->getMessage());
    if(http_response_code()<400) http_response_code($e instanceof InvalidArgumentException?400:500);
    echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Data biaya gagal diproses. Periksa file dan koneksi database.']);
} finally { if($conn) $conn->close(); }
