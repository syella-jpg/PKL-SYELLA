<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/mtm_template_history.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') {
        header('Access-Control-Allow-Private-Network: true');
    }
} elseif ($origin === 'http://127.0.0.1:8000' || $origin === 'http://localhost:8000') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET, OPTIONS');
    echo json_encode(['success' => false, 'message' => 'Endpoint MTM hanya menerima GET.']);
    exit;
}

$year = trim((string)($_GET['year'] ?? ''));
if (!preg_match('/^\d{4}$/', $year)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tahun MTM harus terdiri dari 4 digit.']);
    exit;
}

$requestedPeriod = $_GET['period'] ?? ($year.'-12');
if (!is_string($requestedPeriod) || !preg_match('/^'.preg_quote($year,'/').'-(0[1-9]|1[0-2])$/D', $requestedPeriod)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Periode MTM tidak sesuai dengan tahun yang dipilih.']);
    exit;
}

$conn = openReportConnection();
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database MTM tidak dapat dihubungkan.']);
    exit;
}
$conn->set_charset('utf8mb4');

$data = [];
foreach (['REVENUE', 'EXISTING', 'NEW_PRODUCT'] as $metric) {
    $data[$metric] = ['TARGET' => array_fill(0, 12, null), 'ACTUAL' => array_fill(0, 12, null)];
}
try {
    $records = loadMtmTemplateHistory($conn, $requestedPeriod, false);
} catch (Throwable $error) {
    error_log('Read MTM snapshot: '.$error->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Data MTM gagal dibaca. Periksa koneksi database.']);
    $conn->close();
    exit;
}
$sourcePeriod = null;
foreach ($records as $row) {
    if ($sourcePeriod === null) $sourcePeriod = $row['report_period'];
    if ($row['report_period'] !== $sourcePeriod) break;
    $metric = strtoupper((string)$row['metric_code']);
    $type = strtoupper((string)$row['value_type']);
    $month = (int)$row['month_number'] - 1;
    if (isset($data[$metric]) && $type === 'ROLLING' && !isset($data[$metric]['ROLLING'])) $data[$metric]['ROLLING'] = array_fill(0,12,null);
    if (!isset($data[$metric][$type]) || $month < 0 || $month > 11) continue;
    $data[$metric][$type][$month] = $row['amount'] === null ? null : (float)$row['amount'];
}
$conn->close();

if ($sourcePeriod !== null) {
    foreach (['TARGET', 'ACTUAL'] as $type) {
        for ($month = 0; $month < 12; $month++) {
            if ($data['REVENUE'][$type][$month] === null) {
                $existing = $data['EXISTING'][$type][$month];
                $newProduct = $data['NEW_PRODUCT'][$type][$month];
                if ($existing !== null || $newProduct !== null) {
                    $data['REVENUE'][$type][$month] = (float)($existing ?? 0) + (float)($newProduct ?? 0);
                }
            }
        }
    }
}

echo json_encode(['success' => true, 'period' => $sourcePeriod, 'data' => $sourcePeriod === null ? null : $data]);
