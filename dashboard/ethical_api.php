<?php

require_once __DIR__ . '/../config/database.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') {
        header('Access-Control-Allow-Private-Network: true');
    }
} elseif ($origin === 'http://127.0.0.1:8000' || $origin === 'http://localhost:8000') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET, OPTIONS');
    echo json_encode(['success' => false, 'message' => 'Endpoint ETHICAL hanya menerima GET.']);
    exit;
}

$conn = app_database_connection();
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database ETHICAL tidak dapat dihubungkan.']);
    exit;
}
$conn->set_charset('utf8mb4');

try {
    $result = $conn->query("SELECT report_year, source_row, product_name, month_number, data_type, unit_value, hna_value, hjp_value
        FROM `ETHICAL` ORDER BY report_year, source_row, month_number, data_type");
    if (!$result) throw new RuntimeException('Tabel ETHICAL belum tersedia atau strukturnya belum lengkap.');

    $byYear = [];
    while ($item = $result->fetch_assoc()) {
        $year = (int)$item['report_year'];
        $sourceRow = (int)$item['source_row'];
        if (!isset($byYear[$year][$sourceRow])) {
            $byYear[$year][$sourceRow] = [
                'label' => strtoupper(trim(preg_replace('/\s+/', ' ', (string)$item['product_name']))),
                'months' => [],
            ];
        }
        $month = (int)$item['month_number'];
        $type = strtoupper((string)$item['data_type']);
        $byYear[$year][$sourceRow]['months'][$month][$type] = [
            'unit' => (float)$item['unit_value'],
            'hna' => (float)$item['hna_value'],
            'hjp' => (float)$item['hjp_value'],
        ];
    }
    $result->free();

    $findSection = static function (array $rows, string $startPattern, string $totalPattern): ?array {
        $first = null;
        foreach ($rows as $rowNumber => $row) {
            $label = $row['label'];
            if ($first === null && preg_match($startPattern, $label) && !preg_match('/^TOTAL\b/', $label)) {
                $first = $rowNumber + 1;
            } elseif ($first !== null && preg_match($totalPattern, $label)) {
                return ['first' => $first, 'last' => $rowNumber - 1, 'total' => $rowNumber];
            }
        }
        return null;
    };
    $findNewSections = static function (array $rows): array {
        $sections = [];
        foreach ($rows as $rowNumber => $row) {
            if (!preg_match('/^NEW PRODUCT(?:\s+(?:20\d{2}|\d{2}))?$/', $row['label'])) continue;
            foreach ($rows as $nextRow => $next) {
                if ($nextRow <= $rowNumber) continue;
                if (preg_match('/^NEW PRODUCT(?:\s+(?:20\d{2}|\d{2}))?$/', $next['label'])) break;
                if (preg_match('/^TOTAL NEW PRODUCT(?:\s+\d{2,4})?$/', $next['label'])) {
                    if ($nextRow > $rowNumber + 1) {
                        $sections[] = ['first' => $rowNumber + 1, 'last' => $nextRow - 1, 'total' => $nextRow];
                    }
                    break;
                }
            }
        }
        return $sections;
    };
    $summedSection = static function (array $rows, ?array $section, int $month, string $type, string $metric): float {
        if ($section === null) return 0.0;
        $total = (float)($rows[$section['total']]['months'][$month][$type][$metric] ?? 0);
        if ($total != 0.0) return $total;
        $sum = 0.0;
        for ($row = $section['first']; $row <= $section['last']; $row++) {
            $sum += (float)($rows[$row]['months'][$month][$type][$metric] ?? 0);
        }
        return $sum;
    };

    $data = [];
    foreach ($byYear as $year => $rows) {
        ksort($rows, SORT_NUMERIC);
        $existing = $findSection($rows, '/^EX(?:I|SI)STING PRODUCT$/', '/^TOTAL EX(?:I|SI)STING PRODUCT$/');
        $streamline = $findSection($rows, '/^STREAMLINE$/', '/^TOTAL STREAMLINE$/');
        $newSections = $findNewSections($rows);
        $record = [
            'year' => (int)$year,
            'target' => array_fill(0, 12, 0.0),
            'actual' => array_fill(0, 12, 0.0),
            'newTarget' => array_fill(0, 12, 0.0),
            'newActual' => array_fill(0, 12, 0.0),
            'hnaExisting' => array_fill(0, 12, 0.0),
            'hnaNew' => array_fill(0, 12, 0.0),
        ];
        for ($month = 1; $month <= 12; $month++) {
            foreach (['TARGET' => 'target', 'REAL' => 'actual'] as $type => $output) {
                $record[$output][$month - 1] = $summedSection($rows, $existing, $month, $type, 'hjp')
                    + $summedSection($rows, $streamline, $month, $type, 'hjp');
            }
            $record['hnaExisting'][$month - 1] = $summedSection($rows, $existing, $month, 'TARGET', 'hna')
                + $summedSection($rows, $streamline, $month, 'TARGET', 'hna');
            foreach ($newSections as $section) {
                $record['newTarget'][$month - 1] += $summedSection($rows, $section, $month, 'TARGET', 'hjp');
                $record['newActual'][$month - 1] += $summedSection($rows, $section, $month, 'REAL', 'hjp');
                $record['hnaNew'][$month - 1] += $summedSection($rows, $section, $month, 'TARGET', 'hna');
            }
        }
        $record['hnaTotal'] = array_map(static fn($existingValue, $newValue) => $existingValue + $newValue,
            $record['hnaExisting'], $record['hnaNew']);
        $data[] = $record;
    }
    usort($data, static fn($a, $b) => $b['year'] <=> $a['year']);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

$conn->close();
