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

$conn = new mysqli('127.0.0.1', 'root', '', 'LAPORAN KORPORAT');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database gagal terhubung: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

if (!$conn->query("CREATE TABLE IF NOT EXISTS `revenue_edp_mnj` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    report_period CHAR(7) NULL,
    gb VARCHAR(50) NULL,
    product_code VARCHAR(100) NULL,
    principal_product_code VARCHAR(100) NULL,
    product_name VARCHAR(255) NULL,
    kategori VARCHAR(100) NULL,
    qty_jan DECIMAL(15,2) NOT NULL DEFAULT 0, qty_feb DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_mar DECIMAL(15,2) NOT NULL DEFAULT 0, qty_apr DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_mei DECIMAL(15,2) NOT NULL DEFAULT 0, qty_jun DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_jul DECIMAL(15,2) NOT NULL DEFAULT 0, qty_agt DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_sep DECIMAL(15,2) NOT NULL DEFAULT 0, qty_okt DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_nov DECIMAL(15,2) NOT NULL DEFAULT 0, qty_des DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    rev_jan DECIMAL(18,2) NOT NULL DEFAULT 0, rev_feb DECIMAL(18,2) NOT NULL DEFAULT 0,
    rev_mar DECIMAL(18,2) NOT NULL DEFAULT 0, rev_apr DECIMAL(18,2) NOT NULL DEFAULT 0,
    rev_mei DECIMAL(18,2) NOT NULL DEFAULT 0, rev_jun DECIMAL(18,2) NOT NULL DEFAULT 0,
    rev_jul DECIMAL(18,2) NOT NULL DEFAULT 0, rev_agt DECIMAL(18,2) NOT NULL DEFAULT 0,
    rev_sep DECIMAL(18,2) NOT NULL DEFAULT 0, rev_okt DECIMAL(18,2) NOT NULL DEFAULT 0,
    rev_nov DECIMAL(18,2) NOT NULL DEFAULT 0, rev_des DECIMAL(18,2) NOT NULL DEFAULT 0,
    rev_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    KEY idx_revenue_edp_period (report_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tabel revenue_edp_mnj gagal disiapkan: ' . $conn->error]);
    $conn->close();
    exit;
}
$periodColumn = $conn->query("SHOW COLUMNS FROM `revenue_edp_mnj` LIKE 'report_period'");
if (!$periodColumn || $periodColumn->num_rows === 0) {
    if (!$conn->query('ALTER TABLE `revenue_edp_mnj` ADD COLUMN report_period CHAR(7) NULL')) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Kolom report_period gagal ditambahkan ke revenue_edp_mnj: ' . $conn->error]);
        $conn->close();
        exit;
    }
}

$period = trim((string)($_GET['period'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Periode harus berformat YYYY-MM.']);
    $conn->close();
    exit;
}

// Baris yang sudah ada sebelum kolom periode ditambahkan merupakan upload
// EDP lama; kaitkan dengan periode yang sedang diminta.
$tagLegacy = $conn->prepare('UPDATE revenue_edp_mnj SET report_period = ? WHERE report_period IS NULL');
if ($tagLegacy) {
    $tagLegacy->bind_param('s', $period);
    $tagLegacy->execute();
    $tagLegacy->close();
}

// Catat periode untuk baris EDP lama yang sudah ada sebelum report_period dibuat.
$periodCheck = $conn->prepare('SELECT COUNT(*) FROM revenue_edp_mnj WHERE report_period = ?');
if (!$periodCheck) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Pengecekan periode EDP gagal: ' . $conn->error]);
    $conn->close();
    exit;
}
$periodCheck->bind_param('s', $period);
$periodCheck->execute();
$periodCheck->bind_result($existingRows);
$periodCheck->fetch();
$periodCheck->close();
$sql = "SELECT id, gb, product_code, principal_product_code, product_name, kategori,
        qty_jan, qty_feb, qty_mar, qty_apr, qty_mei, qty_jun, qty_jul, qty_agt,
        qty_sep, qty_okt, qty_nov, qty_des, qty_total,
        rev_jan, rev_feb, rev_mar, rev_apr, rev_mei, rev_jun, rev_jul, rev_agt,
        rev_sep, rev_okt, rev_nov, rev_des, rev_total
        FROM revenue_edp_mnj WHERE report_period = ? ORDER BY id ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query EDP MNJ gagal: ' . $conn->error]);
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
