<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$missing = [];

foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'] as $name) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        $missing[] = $name;
    }
}

echo json_encode([
    'php_aktif' => true,
    'konfigurasi_lengkap' => count($missing) === 0,
    'variabel_belum_diisi' => $missing,
]);
