<?php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') {
        header('Access-Control-Allow-Private-Network: true');
    }
} elseif (in_array($origin, ['http://127.0.0.1:8000', 'http://localhost:8000'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'LAPORAN KORPORAT');
    if ($conn->connect_error) throw new RuntimeException('Database gagal terhubung: ' . $conn->connect_error);
    $conn->set_charset('utf8mb4');
    // Siapkan tabel di endpoint ini juga: upload target bisa menjadi proses
    // pertama yang menyentuh tabel, sebelum halaman dashboard membacanya.
    if (!$conn->query("CREATE TABLE IF NOT EXISTS `revenue_edp_mnj_target` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `product` VARCHAR(50) NOT NULL,
        `periode_tahun` SMALLINT UNSIGNED NOT NULL,
        `bulan` TINYINT UNSIGNED NOT NULL,
        `target` DECIMAL(20,4) NOT NULL DEFAULT 0,
        UNIQUE KEY `uniq_edp_mnj_target` (`product`, `periode_tahun`, `bulan`),
        KEY `idx_edp_mnj_target_year` (`periode_tahun`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        throw new RuntimeException('Tabel revenue_edp_mnj_target gagal disiapkan: ' . $conn->error);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
        if (!$year || $year < 2000 || $year > 2155) {
            http_response_code(400);
            throw new RuntimeException('Tahun harus berupa angka yang valid.');
        }
        $stmt = $conn->prepare('SELECT product, periode_tahun, bulan, target FROM revenue_edp_mnj_target WHERE periode_tahun = ? ORDER BY product, bulan');
        if (!$stmt) throw new RuntimeException('Query target EDP MNJ gagal: ' . $conn->error);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($record = $result->fetch_assoc()) $rows[] = $record;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'year' => $year, 'total' => count($rows), 'data' => $rows]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST, OPTIONS');
        throw new RuntimeException('Endpoint hanya menerima GET atau POST.');
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    $year = filter_var($payload['year'] ?? null, FILTER_VALIDATE_INT);
    $rows = $payload['rows'] ?? null;
    if (!$year || $year < 2000 || $year > 2155 || !is_array($rows) || count($rows) !== 12) {
        http_response_code(400);
        throw new RuntimeException('Target EDP MNJ harus memiliki tahun dan 12 baris EXIST/NP GB 1–6.');
    }

    $seen = [];
    foreach ($rows as $row) {
        $code = (string)($row['code'] ?? '');
        if (!preg_match('/^(existing|new)([1-6])$/', $code, $match) || isset($seen[$code])) {
            http_response_code(400);
            throw new RuntimeException('Kode produk target EDP MNJ tidak valid atau berulang.');
        }
        $seen[$code] = true;
        if (!is_array($row['months'] ?? null) || count($row['months']) !== 12) {
            http_response_code(400);
            throw new RuntimeException('Setiap baris target harus memiliki nilai Januari sampai Desember.');
        }
        foreach ($row['months'] as $value) {
            if (!is_numeric($value) || !is_finite((float)$value)) {
                http_response_code(400);
                throw new RuntimeException('Nilai target bulanan harus berupa angka.');
            }
        }
    }
    for ($gb = 1; $gb <= 6; $gb++) {
        if (!isset($seen['existing' . $gb], $seen['new' . $gb])) {
            http_response_code(400);
            throw new RuntimeException('Target EDP MNJ harus lengkap untuk EXIST dan NP GB 1–6.');
        }
    }

    $conn->begin_transaction();
    $delete = $conn->prepare('DELETE FROM revenue_edp_mnj_target WHERE periode_tahun = ?');
    if (!$delete) throw new RuntimeException('Gagal menyiapkan penggantian target EDP MNJ: ' . $conn->error);
    $delete->bind_param('i', $year);
    if (!$delete->execute()) throw new RuntimeException('Target EDP MNJ lama gagal diganti: ' . $delete->error);
    $delete->close();

    $insert = $conn->prepare('INSERT INTO revenue_edp_mnj_target (product, periode_tahun, bulan, target) VALUES (?, ?, ?, ?)');
    if (!$insert) throw new RuntimeException('Gagal menyiapkan penyimpanan target EDP MNJ: ' . $conn->error);
    foreach ($rows as $row) {
        preg_match('/^(existing|new)([1-6])$/', (string)$row['code'], $match);
        $product = $match[1] === 'existing' ? 'EXIST GB ' . $match[2] : 'NP GB ' . $match[2];
        foreach (array_values($row['months']) as $index => $value) {
            $month = $index + 1;
            $target = (float)$value;
            $insert->bind_param('siid', $product, $year, $month, $target);
            if (!$insert->execute()) throw new RuntimeException('Gagal menyimpan ' . $product . ' bulan ' . $month . ': ' . $insert->error);
        }
    }
    $insert->close();
    $conn->commit();
    $conn->close();
    echo json_encode(['success' => true, 'year' => $year, 'rows' => 12, 'monthly_records' => 144]);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
        $conn->close();
    }
    if (http_response_code() < 400) http_response_code(500);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
}
