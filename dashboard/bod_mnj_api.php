<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/bod_mnj_workbook.php';
require_once __DIR__.'/../includes/report_artifact_cache.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['null', 'http://127.0.0.1:8000', 'http://localhost:8000'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') header('Access-Control-Allow-Private-Network: true');
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
$conn = null;
try {
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST, OPTIONS');
        http_response_code(405);
        throw new InvalidArgumentException('Metode tidak didukung.');
    }
    $payload = $method === 'POST' ? json_decode(file_get_contents('php://input'), true) : [];
    $period = $method === 'POST' ? ($payload['period'] ?? '') : ($_GET['period'] ?? '');
    if (!is_string($period)) throw new InvalidArgumentException('Periode BOD MNJ tidak valid.');
    validateBodMnjPeriod($period);
    if ($method === 'POST') {
        $base64 = $payload['base64'] ?? null;
        if (!is_string($base64)) throw new InvalidArgumentException('File BOD MNJ tidak valid.');
        $book = decodeBodMnjWorkbook($base64, (int)substr($period, 0, 4));
        $book->disconnectWorksheets();
    }
    $conn = openReportConnection();
    if ($method === 'POST') {
        saveBodMnjRecord($conn, $period, $base64);
        echo json_encode(['success' => true, 'period' => $period]);
    } else {
        $record = findBodMnjRecord($conn, $period);
        if ($record && !isset($record['formulas'])) {
            $key=reportArtifactKey([$record['base64'],substr($period,0,4)],[__DIR__.'/../includes/bod_mnj_workbook.php',__DIR__.'/../composer.lock']);
            $json=cachedReportArtifact('bod-mnj-formulas',$key,static function()use($record,$period){
                $book=decodeBodMnjWorkbook($record['base64'],(int)substr($period,0,4));
                try{return json_encode(getBodMnjFormulaMap($book),JSON_THROW_ON_ERROR);}finally{$book->disconnectWorksheets();}
            });
            $record['formulas']=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        }
        echo json_encode(['success' => true, 'data' => $record]);
    }
} catch (InvalidArgumentException $error) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('BOD MNJ upload/read failed: '.$error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Data BOD MNJ gagal diproses. Periksa koneksi database dan konfigurasi server.']);
} finally {
    if ($conn) $conn->close();
}
