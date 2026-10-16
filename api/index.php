<?php

// Route all application PHP endpoints through one Vercel Function. This keeps
// Composer from installing the shared vendor directory concurrently per API.
$script = (string)($_GET['script'] ?? '');
$allowedScripts = [
    'upload/upload.php',
    'dashboard/ethical_api.php',
    'dashboard/mtm_api.php',
    'dashboard/revenue_edp_mnj_api.php',
    'dashboard/revenue_edp_mnj_target_api.php',
    'dashboard/revenue_ibfm_api.php',
    'dashboard/revenue_kx_si_download.php',
    'dashboard/revenue_so_api.php',
    'dashboard/revenue_so_targets_api.php',
    'dashboard/unit_api.php',
];

if (!in_array($script, $allowedScripts, true)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
    exit;
}

require __DIR__ . '/../' . $script;
