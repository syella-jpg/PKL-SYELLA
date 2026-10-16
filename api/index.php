<?php

$endpoints = [
    'dashboard/unit_api.php' => __DIR__ . '/../dashboard/unit_api.php',
    'dashboard/revenue_edp_mnj_api.php' => __DIR__ . '/../dashboard/revenue_edp_mnj_api.php',
    'dashboard/revenue_so_targets_api.php' => __DIR__ . '/../dashboard/revenue_so_targets_api.php',
    'dashboard/mtm_api.php' => __DIR__ . '/../dashboard/mtm_api.php',
    'dashboard/revenue_so_api.php' => __DIR__ . '/../dashboard/revenue_so_api.php',
    'dashboard/ethical_api.php' => __DIR__ . '/../dashboard/ethical_api.php',
    'dashboard/revenue_edp_mnj_target_api.php' => __DIR__ . '/../dashboard/revenue_edp_mnj_target_api.php',
    'dashboard/revenue_ibfm_api.php' => __DIR__ . '/../dashboard/revenue_ibfm_api.php',
    'dashboard/revenue_kx_si_download.php' => __DIR__ . '/../dashboard/revenue_kx_si_download.php',
    'upload/upload.php' => __DIR__ . '/../upload/upload.php',
];

$endpoint = (string)($_GET['endpoint'] ?? '');
if (!isset($endpoints[$endpoint])) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
    exit;
}

require $endpoints[$endpoint];
