<?php
namespace UnitRevenueUploadTest;
// Run: php scripts/test-unit-revenue-upload.php [optional-source.xlsx]
// Uses a connection double; never connects to or modifies a database.
require dirname(__DIR__) . '/vendor/autoload.php';
putenv('DB_HOST');

function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
// In-memory connection double: no connection to the user's database.
class mysqli {
    public array $data = [];
    public array $events = [];
    public array $snapshot = [];
    public bool $transaction = false;
    public bool $locked = false;
    public bool $busy = false;
    public bool $failUnit = false;
    public string $error = '';
    public function query($sql) {
        $this->events[] = $sql;
        if ($sql === 'BEGIN PESSIMISTIC') return $this->begin_transaction();
        return true;
    }
    public function prepare($sql) { return new Statement($this, $sql); }
    public function begin_transaction() {
        $this->snapshot = $this->data; $this->transaction = true; return true;
    }
    public function commit() { $this->transaction = false; $this->locked = false; $this->events[] = 'COMMIT'; }
    public function rollback() {
        if ($this->transaction) $this->data = $this->snapshot;
        $this->transaction = false; $this->locked = false; $this->events[] = 'ROLLBACK';
    }
    public function close() {}
}
class Statement {
    public array $values = [];
    public string $error = '';
    public int $num_rows = 1;
    public function __construct(public mysqli $conn, public string $sql) {}
    public function bind_param($types, &...$values) { $this->values = &$values; return true; }
    public function store_result() { return true; }
    public function close() {}
    public function execute() {
        $sql = $this->sql; $conn = $this->conn; $conn->events[] = $sql;
        if (str_contains($sql, 'FOR UPDATE')) {
            check($conn->transaction, 'Lock acquired outside transaction');
            if ($conn->busy) throw new \Exception('Lock wait timeout', 1205);
            $conn->locked = true;
        } elseif (str_starts_with($sql, 'DELETE FROM')) {
            check($conn->locked, 'Deleted data before obtaining period lock');
            preg_match('/`([^`]+)`/', $sql, $match);
            $conn->data[$match[1]][$this->values[0]] = [];
        } elseif (preg_match('/INSERT INTO `(revenue_edp_mnj|UNIT)`/', $sql, $match)) {
            check($conn->locked, 'Inserted data without period lock');
            if ($match[1] === 'UNIT' && $conn->failUnit) throw new \Exception('Simulated UNIT write failure');
            $width = $match[1] === 'UNIT' ? 19 : 32;
            check(substr_count($sql, '?') === count($this->values), 'Placeholder count differs from values');
            foreach (array_chunk(array_values($this->values), $width) as $values) {
                check(count($values) === $width, 'Incomplete batch row');
                $conn->data[$match[1]][$values[0]][] = $values;
            }
        }
        return true;
    }
}
class Sheet {
    public function __construct(public $sheet, public mysqli $conn) {}
    public function getHighestRow() { return $this->sheet->getHighestRow(); }
    public function rangeToArray(...$args) {
        check(!$this->conn->transaction, 'Spreadsheet calculation occurred while holding transaction locks');
        return $this->sheet->rangeToArray(...$args);
    }
}
$source = file_get_contents(dirname(__DIR__) . '/upload/upload.php');
$start = strpos($source, '    $sql = "' . "\n" . '        INSERT INTO `{$tableName}`');
check($start !== false, 'Upload transaction block not found');
$program = 'namespace UnitRevenueUploadTest; use \\Exception; use \\Throwable; try {' . substr($source, $start);
function upload(mysqli $conn, $rawSheet, $program) {
    $divisi = 'REVENUE VERSI EDP MNJ'; $tableName = 'revenue_edp_mnj'; $period = '2026-10';
    // Both requests saw an empty period before the first request committed.
    $existingRows = 0; $replaceExistingPeriod = true; $sheet = new Sheet($rawSheet, $conn);
    http_response_code(200); ob_start(); eval($program); $result = json_decode(ob_get_clean(), true);
    return [$result, http_response_code()];
}
if (isset($argv[1])) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($argv[1]);
} else {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array_merge(['GB 1', 'P1', 'PR1', 'Product', 'ABC'], range(1,26)), null, 'A5');
    $sheet->fromArray(array_merge(['GB 2', 'P2', 'PR2', 'Product 2', 'D'], range(27,52)), null, 'A7');
}
$raw = $spreadsheet->getActiveSheet();
$expected = 0;
$sourceRows = [];
foreach ($raw->rangeToArray('A5:AE' . $raw->getHighestRow(), null, true, false, false) as $values) {
    if (array_filter($values, static fn($v) => $v !== null && trim((string)$v) !== '')) {
        $expected++;
        $sourceRows[] = $values;
    }
}
$conn = new mysqli();
$conn->data = ['revenue_edp_mnj' => ['2026-09' => [['old revenue']]], 'UNIT' => ['2026-09' => [['old unit']]]];
foreach (['local', 'tidb'] as $mode) {
    putenv('VERCEL=' . ($mode === 'tidb' ? '1' : '0'));
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $eventStart = count($conn->events);
        [$result, $status] = upload($conn, $raw, $program);
        check($status === 200 && $result['success'] && $result['rows_inserted'] === $expected, 'Upload failed');
        foreach (['revenue_edp_mnj','UNIT'] as $table) check(count($conn->data[$table]['2026-10']) === $expected, 'Repeated upload appended rows');
        check($conn->data['revenue_edp_mnj']['2026-09'] === [['old revenue']], 'Another period was changed');
        $writes = array_filter(array_slice($conn->events, $eventStart), static fn($sql) =>
            preg_match('/INSERT INTO `(revenue_edp_mnj|UNIT)`/', $sql));
        check(count($writes) === 2 * (int)ceil($expected / 100), 'Upload did not batch inserts');
    }
}
foreach ($sourceRows as $index => $values) {
    $revenue = $conn->data['revenue_edp_mnj']['2026-10'][$index];
    $unit = $conn->data['UNIT']['2026-10'][$index];
    for ($i=0; $i<5; $i++) {
        check($revenue[1+$i] === trim((string)$values[$i]), 'Revenue identity changed');
        check($unit[1+$i] === trim((string)$values[$i]), 'UNIT identity changed');
    }
    for ($i=0; $i<13; $i++) {
        check($revenue[6+$i] == 0, 'QTY leaked into Revenue');
        check($unit[6+$i] == (float)($values[5+$i] ?? 0), 'UNIT values changed');
        check($revenue[19+$i] == (float)($values[18+$i] ?? 0), 'Revenue values changed');
    }
}
$before = $conn->data;
$conn->failUnit = true;
[$result, $status] = upload($conn, $raw, $program);
check(!$result['success'] && $status === 500 && $conn->data === $before, 'Partial write was not rolled back');
$conn->failUnit = false; $conn->busy = true;
[$result, $status] = upload($conn, $raw, $program);
check(!$result['success'] && $status === 409 && $conn->data === $before, 'Busy upload changed data or returned wrong status');
$conn->busy = false;
$empty = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
[$result, $status] = upload($conn, $empty->getActiveSheet(), $program);
check(!$result['success'] && $conn->data === $before, 'Empty file erased old data');
echo "PASS: {$expected} source rows in " . (2 * (int)ceil($expected / 100)) . " INSERTs; repeated stale-count uploads replace both tables, all identities and amounts preserved, failures rollback, busy returns 409, empty file preserves data.\n";
echo "NOTE: connection double; not a live MySQL/TiDB concurrency test.\n";
