<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/database.php';

$connection = null;
try {
    $connection = openTidbConnection();
    $result = $connection->query('SELECT 1 AS connection_ok');
    $row = $result->fetch_assoc();
    $result->free();
    if ((int) $row['connection_ok'] !== 1) {
        throw new RuntimeException('Query pemeriksaan gagal.');
    }
    $result = $connection->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'");
    $status = $result->fetch_assoc();
    $result->free();
    if (empty($status['Value'])) {
        throw new RuntimeException('Koneksi TLS tidak aktif.');
    }
    echo json_encode(['success' => true, 'database_terhubung' => true, 'tls_aktif' => true]);
} catch (Throwable $error) {
    http_response_code(503);
    $code = (int) $error->getCode();
    $reason = match ($code) {
        1045 => 'Autentikasi ditolak. Periksa DB_USER dan DB_PASSWORD di Vercel.',
        1049 => 'Database tidak ditemukan. Periksa DB_NAME.',
        2002, 2003 => 'Server tidak dapat dijangkau. Periksa host, port, dan pengaturan akses TiDB.',
        2026 => 'Verifikasi TLS gagal. Periksa sertifikat CA dan hostname.',
        default => 'Periksa konfigurasi, sertifikat CA, dan akses jaringan TiDB.',
    };
    echo json_encode([
        'success' => false,
        'database_terhubung' => false,
        'kode' => $code,
        'message' => $reason,
    ]);
} finally {
    if ($connection instanceof mysqli) {
        $connection->close();
    }
}
