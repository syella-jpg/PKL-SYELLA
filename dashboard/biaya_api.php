<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Release a small reserve so PHP can return JSON after a memory exhaustion error.
$biayaErrorReserve = str_repeat(' ', 65536);
register_shutdown_function(function () use (&$biayaErrorReserve) {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    $biayaErrorReserve = null;
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false, 'message'=>'Proses upload BIAYA berhenti di server. Periksa Runtime Logs Vercel atau terminal PHP.']);
    }
});
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
        $bytes=file_get_contents($file['tmp_name']);
        if ($bytes === false || $bytes === '') throw new InvalidArgumentException('File biaya kosong atau gagal dibaca.');
        $filename=basename(str_replace('\\', '/', $file['name'] ?? ''));
        $extension=$reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods ? 'ods' : 'xlsx';
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== $extension) throw new InvalidArgumentException('Ekstensi file harus sesuai format ODS atau XLSX.');
        $book=$reader->load($file['tmp_name']); $data=parseBiayaWorkbook($book,(int)substr($period,0,4)); $book->disconnectWorksheets();
    }
    if ($method==='POST') {
        $data['base64']=base64_encode($bytes);
        $data['filename']=$filename;
        $data['sha256']=hash('sha256', $bytes);
    }
    $missingConfig = [];
    foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'] as $key) {
        if (getenv($key) === false || getenv($key) === '') $missingConfig[] = $key;
    }
    if ($missingConfig) {
        http_response_code(503);
        throw new InvalidArgumentException('Konfigurasi TiDB pada server belum lengkap: ' . implode(', ', $missingConfig) . '. Atur pada server yang menjalankan halaman ini, lalu restart server PHP.');
    }
    $conn=openTidbConnection();
    if($method==='POST') {
        $conn->query('CREATE TABLE IF NOT EXISTS biaya_files (report_period CHAR(7) PRIMARY KEY, payload LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $q=$conn->prepare('INSERT INTO biaya_files (report_period,payload) VALUES (?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)');
        $payload=json_encode($data,JSON_THROW_ON_ERROR); $q->bind_param('ss',$period,$payload);
        if(!$q->execute()) throw new RuntimeException('Biaya gagal disimpan.'); $q->close();
    } else $data=findBiayaRecord($conn,$period);
    echo json_encode(['success'=>true,'data'=>$method==='POST' ? ['period'=>$period,'filename'=>$filename,'sha256'=>$data['sha256']] : $data]);
} catch(Throwable $e) {
    // Log the error category without database credentials or uploaded content.
    error_log('BIAYA upload/API failed: ' . get_class($e) . ' code=' . $e->getCode());
    if(http_response_code()<400) http_response_code($e instanceof InvalidArgumentException?400:500);
    echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Data biaya gagal diproses. Periksa file dan koneksi database.']);
} finally { if($conn) $conn->close(); }
