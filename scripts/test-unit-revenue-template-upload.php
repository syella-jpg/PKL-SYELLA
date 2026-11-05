<?php
namespace UnitRevenueUploadTest;

// Uses the existing connection double: no database connection or private data.
class TemplateSnapshotFailure extends \RuntimeException {}

function saveUnitRevenueTemplateSnapshot($conn, string $period, ?array $records): void
{
    check($conn->transaction && $conn->locked, 'Template snapshot saved outside the locked upload transaction');
    $conn->events[] = 'SAVE TEMPLATE SNAPSHOT';
    if ($conn instanceof TemplateSnapshotConnection && $conn->failSnapshot) {
        throw new TemplateSnapshotFailure('Simulated template snapshot failure');
    }
    if ($records === null) unset($conn->data['template_snapshots'][$period]);
    else $conn->data['template_snapshots'][$period] = $records;
}

require __DIR__ . '/test-unit-revenue-upload.php';

class TemplateSnapshotConnection extends mysqli
{
    public bool $failSnapshot = false;
}

function uploadTemplateSnapshot(TemplateSnapshotConnection $conn, $rawSheet, string $program, ?array $records): array
{
    $divisi = 'REVENUE VERSI EDP MNJ';
    $tableName = 'revenue_edp_mnj';
    $period = '2026-10';
    $existingRows = 0;
    $replaceExistingPeriod = true;
    $unitRevenueTemplateRecords = $records;
    $unitRevenueSnapshotReady = true;
    $sheet = new Sheet($rawSheet, $conn);
    http_response_code(200);
    ob_start();
    eval($program);
    return [json_decode(ob_get_clean(), true), http_response_code()];
}

$source = file_get_contents(dirname(__DIR__) . '/upload/upload.php');
$validation = strpos($source, 'parseUnitRevenueInputTemplate($spreadsheet, $period)');
$storeSetup = strpos($source, 'ensureUnitRevenueTemplateStore($conn)');
$lockedTransaction = strpos($source, '$highestRow = $sheet->getHighestRow()');
check($validation !== false && $validation < $storeSetup && $storeSetup < $lockedTransaction,
    'Compact validation or snapshot DDL moved inside the data replacement transaction');
$flush = strrpos($source, 'if ($unitStmt) $flushEdpBatch();');
$save = strrpos($source, 'saveUnitRevenueTemplateSnapshot($conn, $period, $unitRevenueTemplateRecords ?? null);');
$commit = strpos($source, '$conn->commit();', $flush);
check($flush < $save && $save < $commit, 'Snapshot must save after data batches and before the same commit');

$conn = new TemplateSnapshotConnection();
$conn->data = ['template_snapshots' => ['2026-09' => [['saved' => 'earlier period']]]];
$records = [['product_code' => 'SYNTHETIC-ONLY', 'unit' => [], 'revenue' => []]];
[$result, $status] = uploadTemplateSnapshot($conn, $raw, $program, $records);
check($result['success'] && $status === 200, 'Compact snapshot upload failed');
check($conn->data['template_snapshots']['2026-10'] === $records, 'Compact snapshot was not stored');
check($conn->data['template_snapshots']['2026-09'] === [['saved' => 'earlier period']], 'Another snapshot period changed');

$before = $conn->data;
$conn->failSnapshot = true;
[$result, $status] = uploadTemplateSnapshot($conn, $raw, $program, [['replacement' => true]]);
check(!$result['success'] && $status === 500 && $conn->data === $before,
    'Snapshot failure did not roll back UNIT, Revenue, and snapshot together');
$conn->failSnapshot = false;

$conn->failUnit = true;
[$result, $status] = uploadTemplateSnapshot($conn, $raw, $program, [['replacement' => true]]);
check(!$result['success'] && $conn->data === $before, 'UNIT failure changed the compact snapshot');
$conn->failUnit = false;

[$result, $status] = uploadTemplateSnapshot($conn, $raw, $program, null);
check($result['success'] && $status === 200 && !isset($conn->data['template_snapshots']['2026-10']),
    'Legacy upload kept a stale compact template snapshot');
check(isset($conn->data['template_snapshots']['2026-09']), 'Legacy upload cleared another snapshot period');

echo "PASS: compact snapshots share the locked UNIT/Revenue transaction, failures preserve all data, legacy replacements clear only their own stale snapshot, validation and DDL precede transaction.\n";
