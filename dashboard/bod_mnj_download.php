<?php
// Send the stored ODS bytes directly: never load/save through a spreadsheet writer.
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/bod_mnj_workbook.php';

$conn = null;
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        header('Allow: GET');
        http_response_code(405);
        exit;
    }
    $period = $_GET['period'] ?? '';
    if (!is_string($period)) throw new InvalidArgumentException('Periode tidak valid.');
    validateBodMnjPeriod($period);
    $conn = openTidbConnection();
    $query = $conn->prepare('SELECT payload FROM bod_mnj_files WHERE report_period = ?');
    $query->bind_param('s', $period);
    $query->execute();
    $record = $query->get_result()->fetch_assoc();
    $query->close();
    if (!$record) {
        http_response_code(404);
        throw new InvalidArgumentException('File asli BOD MNJ untuk periode ini belum disimpan.');
    }
    $payload = json_decode($record['payload'], true, 512, JSON_THROW_ON_ERROR);
    $bytes = base64_decode($payload['base64'] ?? '', true);
    if (!is_string($bytes) || $bytes === '') throw new RuntimeException('File tidak valid.');
    if (isset($payload['sha256']) && !hash_equals($payload['sha256'], hash('sha256', $bytes))) {
        throw new RuntimeException('Integritas file gagal.');
    }
    $filename = basename(str_replace('\\', '/', $payload['filename'] ?? ('BOD-MNJ-' . $period . '.ods')));
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($extension === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/vnd.oasis.opendocument.spreadsheet'));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
} catch (Throwable $error) {
    if (http_response_code() < 400) http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $error instanceof InvalidArgumentException
        ? $error->getMessage() : 'Unduhan BOD MNJ gagal. Periksa koneksi dan tabel bod_mnj_files.']);
} finally {
    if ($conn) $conn->close();
}
