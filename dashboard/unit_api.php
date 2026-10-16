<?php

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

$conn = new mysqli(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', getenv('DB_NAME') ?: 'LAPORAN KORPORAT');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database gagal terhubung: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

$period = trim((string)($_GET['period'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Periode harus berformat YYYY-MM.']);
    $conn->close();
    exit;
}

$sql = 'SELECT id, report_period, gb, product_code, principal_product_code, product_name, kategori,
    unit_jan, unit_feb, unit_mar, unit_apr, unit_mei, unit_jun, unit_jul, unit_agt,
    unit_sep, unit_okt, unit_nov, unit_des, unit_total
    FROM `UNIT` WHERE report_period = ? ORDER BY id ASC';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query tabel UNIT gagal: ' . $conn->error]);
    $conn->close();
    exit;
}
$stmt->bind_param('s', $period);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) $data[] = $row;

echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
$stmt->close();
$conn->close();
