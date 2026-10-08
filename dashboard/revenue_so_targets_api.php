<?php

// Target summaries are shared between the Upload and Dashboard pages through
// MySQL, including when either page is opened from file://.
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
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'LAPORAN KORPORAT');
    if ($conn->connect_error) throw new RuntimeException('Database gagal terhubung: ' . $conn->connect_error);
    $conn->set_charset('utf8mb4');

    if ($method === 'GET') {
        $year = (string)($_GET['year'] ?? '');
        if (!preg_match('/^20\d{2}$/', $year)) {
            http_response_code(400);
            throw new RuntimeException('Tahun harus berformat YYYY.');
        }
        $prefix = $year . ' | %';
        $stmt = $conn->prepare('SELECT product, januari, februari, maret, april, mei, juni, juli, agustus, september, oktober, november, desember FROM target_revenue WHERE product LIKE ? ORDER BY no');
        if (!$stmt) throw new RuntimeException('Query Target Revenue gagal: ' . $conn->error);
        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($record = $result->fetch_assoc()) {
            if (!preg_match('/^\d{4} \| GB ([1-6]) \| (Kategori ([ABCD])|Non Target|Existing|New Product)$/u', (string)$record['product'], $match)) continue;
            $gb = $match[1];
            if (isset($match[3]) && $match[3] !== '') {
                $code = 'category' . $gb . $match[3];
            } elseif ($match[2] === 'Existing') {
                $code = 'existing' . $gb;
            } elseif ($match[2] === 'New Product') {
                $code = 'new' . $gb;
            } else {
                $code = 'nonTarget' . $gb;
            }
            $months = array_map('floatval', array_slice(array_values($record), 1));
            $rows[] = ['code' => $code, 'months' => $months];
        }
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'data' => $rows ? ['year' => (int)$year, 'rows' => $rows] : null]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST, OPTIONS');
        throw new RuntimeException('Endpoint hanya menerima GET atau POST.');
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    $year = isset($payload['year']) ? (string)$payload['year'] : '';
    $month = (int)($payload['month'] ?? 12);
    $rows = $payload['rows'] ?? null;
    if (!preg_match('/^20\d{2}$/', $year) || $month < 1 || $month > 12 || !is_array($rows) || !$rows || count($rows) > 100) {
        http_response_code(400);
        throw new RuntimeException('Data Target Revenue tidak lengkap atau formatnya tidak valid.');
    }

    $seen = [];
    foreach ($rows as $row) {
        $code = (string)($row['code'] ?? '');
        if (!preg_match('/^(?:category[1-6][ABCD]|nonTarget[1-6]|existing[1-6]|new[1-6])$/', $code) || isset($seen[$code])) {
            http_response_code(400);
            throw new RuntimeException('Kode kategori Target Revenue tidak valid atau berulang.');
        }
        $seen[$code] = true;
        if (!is_array($row['months'] ?? null) || count($row['months']) !== 12) {
            http_response_code(400);
            throw new RuntimeException('Setiap kategori harus memiliki 12 nilai bulanan.');
        }
        foreach ($row['months'] as $value) {
            if (!is_numeric($value) || !is_finite((float)$value)) {
                http_response_code(400);
                throw new RuntimeException('Nilai bulanan Target Revenue harus berupa angka.');
            }
        }
    }

    $conn->begin_transaction();
    $prefix = $year . ' | %';
    $delete = $conn->prepare('DELETE FROM target_revenue WHERE product LIKE ?');
    if (!$delete) throw new RuntimeException('Gagal menyiapkan penggantian Target Revenue: ' . $conn->error);
    $delete->bind_param('s', $prefix);
    if (!$delete->execute()) throw new RuntimeException('Data Target Revenue lama gagal diganti: ' . $delete->error);
    $delete->close();

    $insert = $conn->prepare('INSERT INTO target_revenue (product, januari, februari, maret, april, mei, juni, juli, agustus, september, oktober, november, desember, total_tahun, ytd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$insert) throw new RuntimeException('Gagal menyiapkan penyimpanan Target Revenue: ' . $conn->error);
    foreach ($rows as $row) {
        if (preg_match('/^category([1-6])([ABCD])$/', $row['code'], $match)) {
            $label = 'Kategori ' . $match[2];
            $gb = $match[1];
        } elseif (preg_match('/^nonTarget([1-6])$/', $row['code'], $match)) {
            $label = 'Non Target';
            $gb = $match[1];
        } elseif (preg_match('/^existing([1-6])$/', $row['code'], $match)) {
            $label = 'Existing';
            $gb = $match[1];
        } elseif (preg_match('/^new([1-6])$/', $row['code'], $match)) {
            $label = 'New Product';
            $gb = $match[1];
        } else {
            continue;
        }
        $product = $year . ' | GB ' . $gb . ' | ' . $label;
        $months = array_map('floatval', array_values($row['months']));
        $month1 = $months[0]; $month2 = $months[1]; $month3 = $months[2]; $month4 = $months[3];
        $month5 = $months[4]; $month6 = $months[5]; $month7 = $months[6]; $month8 = $months[7];
        $month9 = $months[8]; $month10 = $months[9]; $month11 = $months[10]; $month12 = $months[11];
        $total = array_sum($months);
        $ytd = array_sum(array_slice($months, 0, $month));
        $insert->bind_param('sdddddddddddddd', $product, $month1, $month2, $month3, $month4, $month5, $month6, $month7, $month8, $month9, $month10, $month11, $month12, $total, $ytd);
        if (!$insert->execute()) throw new RuntimeException('Gagal menyimpan ' . $product . ': ' . $insert->error);
    }
    $insert->close();
    $conn->commit();
    $conn->close();

    echo json_encode(['success' => true, 'year' => (int)$year, 'rows' => count($rows)]);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->errno === 0) $conn->rollback();
        $conn->close();
    }
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
}
