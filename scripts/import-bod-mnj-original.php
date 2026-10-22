<?php
// CLI only. Uses the same DB_* and verified TLS configuration as Vercel.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/database.php';

$conn = null;
try {
    // Require explicit cloud configuration; never accidentally import into local MySQL.
    $conn = openTidbConnection();
    $sql = file_get_contents(__DIR__ . '/../database/bod_mnj_seed.sql');
    if ($sql === false) throw new RuntimeException('SQL impor tidak tersedia.');
    $conn->multi_query($sql);
    do {
        if ($result = $conn->store_result()) $result->free();
    } while ($conn->more_results() && $conn->next_result());
    $record = $conn->query("SELECT payload FROM laporan_korporat.bod_mnj_files WHERE report_period = '2026-06'")->fetch_assoc();
    $record = $record ? json_decode($record['payload'], true, 512, JSON_THROW_ON_ERROR) : null;
    $bytes = $record ? base64_decode($record['base64'] ?? '', true) : false;
    $expected = '600058869cc7c7d9762d9b74f6523d273ab35cd88a6c5fb4f02529fae3f9a8c7';
    if (!$record || !hash_equals($expected, $record['sha256']) || !is_string($bytes) || !hash_equals($expected, hash('sha256', $bytes))) {
        throw new RuntimeException('File tersimpan berbeda dari sumber. Data yang sudah ada tidak ditimpa.');
    }
    echo "PASS: BOD MNJ 2026-06 tersimpan di TiDB; SHA-256 sama dengan file asli.\n";
} catch (Throwable $error) {
    // Do not print connection details or credentials.
    fwrite(STDERR, "Impor/verifikasi gagal. Periksa DB_*, TLS, izin tabel, dan data periode 2026-06.\n");
    exit(1);
} finally {
    if ($conn) $conn->close();
}
