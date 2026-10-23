<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/bod_mnj_workbook.php';

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
        $filename = $payload['filename'] ?? null;
        if (!is_string($filename) || !preg_match('/\.(ods|xlsx)$/i', $filename)) {
            throw new InvalidArgumentException('Pilih file ODS atau XLSX.');
        }
        $filename = basename(str_replace('\\', '/', $filename));
        $book = decodeBodMnjWorkbook($base64, (int)substr($period, 0, 4), false);
        $book->disconnectWorksheets();
    }
    $conn = openReportConnection();
    if ($method === 'POST') {
        saveBodMnjRecord($conn, $period, $base64, $filename);
        echo json_encode(['success' => true, 'period' => $period]);
    } else {
        $record = findBodMnjRecord($conn, $period);
        if ($record) {
            $book = decodeBodMnjWorkbook($record['base64'], (int)substr($period, 0, 4));
            $record['formulas'] = getBodMnjFormulaMap($book);
            $book->disconnectWorksheets();
        }
        echo json_encode(['success' => true, 'data' => $record]);
    }
} catch (InvalidArgumentException $error) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('BOD MNJ API: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Data BOD MNJ gagal diproses. Periksa koneksi database dan konfigurasi server.']);
} finally {
    if ($conn) $conn->close();
}
