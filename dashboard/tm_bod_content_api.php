<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/tm_bod_content_codec.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['null','http://127.0.0.1:8000','http://localhost:8000'], true)) {
    header('Access-Control-Allow-Origin: '.$origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
$conn = null;
try {
    if (!in_array($method, ['GET','POST'], true)) { http_response_code(405); throw new InvalidArgumentException('Metode tidak didukung.'); }
    $data = $method === 'POST' ? json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) : $_GET;
    $year = filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);
    $quarter = $data['quarter'] ?? '';
    if (!$year || $year < 2025 || $year > 2100 || !is_string($quarter) || !preg_match('/^Q[1-4]$/', $quarter)) throw new InvalidArgumentException('Tahun atau kuartal tidak valid.');
    $conn = openReportConnection();
    $conn->query("SET time_zone='+07:00'");
    $conn->query("CREATE TABLE IF NOT EXISTS tm_bod_quarter_content (
        report_year SMALLINT UNSIGNED NOT NULL, quarter CHAR(2) NOT NULL,
        content_json LONGTEXT NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
        updated_by VARCHAR(150) NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (report_year,quarter)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ($method === 'GET') {
        $q = $conn->prepare('SELECT content_json,version,updated_by,updated_at FROM tm_bod_quarter_content WHERE report_year=? AND quarter=?');
        $q->bind_param('is', $year, $quarter); $q->execute(); $row = $q->get_result()->fetch_assoc(); $q->close();
        echo json_encode(['success'=>true,'data'=>$row ? decodeTmBodContent($row['content_json']) : null,'version'=>(int)($row['version']??0),'updatedBy'=>$row['updated_by']??null,'updatedAt'=>$row['updated_at']??null], JSON_THROW_ON_ERROR); exit;
    }
    $user = $data['user'] ?? '';
    if (!is_string($user) || !in_array($user, ['test 1','admin 1','admin 2'], true)) { http_response_code(403); throw new InvalidArgumentException('Login terlebih dahulu untuk menyimpan analisis.'); }
    $version = $data['version'] ?? null;
    $content = $data['content'] ?? null;
    if (!is_int($version) || $version < 0 || !is_array($content)) throw new InvalidArgumentException('Data penyimpanan tidak valid.');
    $allowed = [$quarter, $quarter.'-section-ids', $quarter.'-section-values', $quarter.'-custom-slides'];
    if (array_diff(array_keys($content), $allowed) || !is_array($content[$quarter] ?? null) || !is_array($content[$quarter.'-section-ids'] ?? null) || !is_array($content[$quarter.'-section-values'] ?? null) || !is_array($content[$quarter.'-custom-slides'] ?? null)) throw new InvalidArgumentException('Struktur isi kuartal tidak valid.');
    if (count($content[$quarter]) > 190 || count($content[$quarter.'-custom-slides']) > 190) throw new InvalidArgumentException('Jumlah slide melampaui batas.');
    $clearing = ($data['action'] ?? '') === 'clear';
    if ($clearing) $content = [$quarter=>[], $quarter.'-section-ids'=>[], $quarter.'-section-values'=>(object)[], $quarter.'-custom-slides'=>[]];
    $json = encodeTmBodContent($content);
    $packet = (int)$conn->query('SELECT @@max_allowed_packet AS packet_limit')->fetch_assoc()['packet_limit'];
    if (strlen($json) + 16384 > $packet) throw new InvalidArgumentException('Ukuran analisis masih melebihi batas paket database setelah kompresi. Perkecil gambar atau naikkan max_allowed_packet MySQL menjadi minimal 8 MB.');
    if ($clearing) $conn->begin_transaction();
    if ($version === 0) {
        $q = $conn->prepare('INSERT INTO tm_bod_quarter_content (report_year,quarter,content_json,updated_by) VALUES (?,?,?,?)');
        $q->bind_param('isss', $year, $quarter, $json, $user);
        try { $q->execute(); } catch (mysqli_sql_exception $e) { if ((int)$e->getCode() === 1062) { http_response_code(409); throw new InvalidArgumentException('Kuartal sudah disimpan dari sesi lain. Muat ulang halaman sebelum menyimpan.'); } throw $e; }
    } else {
        $q = $conn->prepare('UPDATE tm_bod_quarter_content SET content_json=?,updated_by=?,updated_at=NOW(),version=version+1 WHERE report_year=? AND quarter=? AND version=?');
        $q->bind_param('ssisi', $json, $user, $year, $quarter, $version); $q->execute();
        if ($q->affected_rows !== 1) { http_response_code(409); throw new InvalidArgumentException('Isi kuartal berubah dari sesi lain. Muat ulang halaman sebelum menyimpan agar tidak menimpa perubahan tersebut.'); }
    }
    $q->close();
    if ($clearing) {
        $first = $year.'-'.str_pad(((int)$quarter[1]-1)*3+1,2,'0',STR_PAD_LEFT);
        $last = $year.'-'.str_pad((int)$quarter[1]*3,2,'0',STR_PAD_LEFT);
        try {
            $delete = $conn->prepare('DELETE FROM tm_bod_analysis_transfers WHERE report_period BETWEEN ? AND ?');
            $delete->bind_param('ss',$first,$last); $delete->execute(); $delete->close();
        } catch (mysqli_sql_exception $e) { if ((int)$e->getCode() !== 1146) throw $e; }
        $conn->commit();
    }
    echo json_encode(['success'=>true,'version'=>$version+1], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($conn) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
    error_log('TM BOD content: '.$e->getMessage());
    if (http_response_code()<400) http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    $message = 'Analisis belum tersimpan di database. Periksa koneksi database dan log server.';
    if ($e instanceof InvalidArgumentException) $message = $e->getMessage();
    elseif ($e instanceof mysqli_sql_exception) {
        $code = (int)$e->getCode();
        if (in_array($code,[1153,2006,2020],true)) $message = 'Koneksi database terputus atau ukuran analisis melewati batas paket database. Perkecil gambar lalu coba simpan kembali. (kode '.$code.')';
        elseif (in_array($code,[1054,1146],true)) $message = 'Struktur tabel tm_bod_quarter_content belum sesuai. Jalankan migrasi 2026-10-29-tm-bod-quarter-content.sql pada database yang digunakan. (kode '.$code.')';
        elseif (in_array($code,[1044,1045,1142],true)) $message = 'Akun database belum memiliki izin yang diperlukan untuk menyimpan TM BOD. (kode '.$code.')';
        else $message = 'Penyimpanan TM BOD gagal di database (kode '.$code.'). Periksa log server.';
    }
    echo json_encode(['success'=>false,'message'=>$message]);
} finally { if ($conn) $conn->close(); }
