<?php

ini_set('display_errors', '0');
header('Cache-Control: no-store');

// Only these application endpoints may be executed by this function.
$endpoints = [
    'biaya_api', 'bod_mnj_api', 'unit_api', 'ethical_api', 'mtm_api', 'revenue_so_api',
    'revenue_so_targets_api', 'revenue_edp_mnj_api',
    'revenue_edp_mnj_target_api', 'revenue_ibfm_api',
    'revenue_kx_si_download', 'tm_bod_download',
];
$endpoint = $_GET['endpoint'] ?? '';
unset($_GET['endpoint']);
if (!is_string($endpoint) || ($endpoint !== 'upload' && !in_array($endpoint, $endpoints, true))) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
    exit;
}

try {
    require $endpoint === 'upload'
        ? __DIR__ . '/../upload/upload.php'
        : __DIR__ . '/../dashboard/' . $endpoint . '.php';
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'API laporan gagal. Periksa konfigurasi database dan skema tabel TiDB.']);
}
