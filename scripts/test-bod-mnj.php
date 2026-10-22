<?php
// php scripts/test-bod-mnj.php [directory containing browser test exports]
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/bod_mnj_workbook.php';

function checkBod(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function compareBodBlock($source, $target, int $offset, bool $styles = true, bool $normalizeText = false): void
{
    $last = getBodMnjLastDataRow($source);
    foreach ($source->getCellCollection()->getCoordinates() as $address) {
        [$column, $row] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($address);
        if ($row > $last) continue;
        $destination = $column . ($row + $offset);
        $sourceCell = $source->getCell($address);
        $isSummary = $source->getCell('A3')->getValue() === 'NO' && $row >= 5 && $row <= 16 && in_array($column, ['C', 'D', 'E', 'I'], true);
        if ($column === 'C' || $isSummary) continue; // YTD and its summary intentionally change.
        if ($sourceCell->isFormula() && preg_match('/\$?C\$?\d+/', $sourceCell->getValue()) && in_array($column, range('D', 'O'), true)) {
            $difference = abs((float)$sourceCell->getOldCalculatedValue() - (float)$target->getCell($destination)->getOldCalculatedValue());
            checkBod($difference < 0.0001, 'Monthly residual amount changed: ' . $destination);
            continue;
        }
        $expected = $source->getCell($address)->getValue();
        $actual = $target->getCell($destination)->getValue();
        // ODF readers differ in collapsing literal whitespace in text paragraphs.
        if ($normalizeText && is_string($expected) && is_string($actual) && !$source->getCell($address)->isFormula()) {
            $expected = preg_replace('/\s+/u', ' ', trim($expected));
            $actual = preg_replace('/\s+/u', ' ', trim($actual));
        }
        checkBod($actual === $expected, 'Cell/formula changed at ' . $destination . ': ' . json_encode([$expected, $actual]));
        if ($styles) checkBod($source->getStyle($address)->getHashCode() === $target->getStyle($destination)->getHashCode(), 'Style changed at ' . $destination);
    }
}
// A small database adapter exercises upserts and year/month selection without a live database.
class BodTestConnection
{
    public array $records = [];
    public function query($sql) { return true; }
    public function prepare($sql) { return new BodTestQuery($this, $sql); }
}
class BodTestQuery
{
    private BodTestConnection $conn;
    private string $sql;
    private array $args;
    public function __construct($conn, $sql) { $this->conn = $conn; $this->sql = $sql; }
    public function bind_param($types, &...$args) { $this->args = &$args; }
    public function execute() {
        if (str_starts_with($this->sql, 'INSERT')) $this->conn->records[$this->args[0]] = $this->args[1];
        return true;
    }
    public function get_result() { return $this; }
    public function fetch_assoc() {
        $records = $this->conn->records;
        krsort($records);
        foreach ($records as $period => $payload) {
            if ($period >= $this->args[0] && $period <= $this->args[1]) return ['report_period' => $period, 'payload' => $payload];
        }
        return null;
    }
    public function close() {}
}

$base = __DIR__ . '/../';
$bytes = [];
foreach ([2025, 2026] as $year) {
    $bytes[$year] = file_get_contents($base . 'dashboard/assets/bod-mnj-' . $year . '.ods');
    $valid = decodeBodMnjWorkbook(base64_encode($bytes[$year]), $year);
    $valid->disconnectWorksheets();
    try {
        decodeBodMnjWorkbook(base64_encode($bytes[$year]), $year === 2025 ? 2026 : 2025);
        throw new RuntimeException('Wrong-year file accepted');
    } catch (InvalidArgumentException $expected) {}
}
foreach (['', '2026-13', '2026-00', '2024-12'] as $badPeriod) {
    try { validateBodMnjPeriod($badPeriod); throw new RuntimeException('Invalid period accepted'); }
    catch (InvalidArgumentException $expected) {}
}
try { decodeBodMnjWorkbook('invalid', 2026); throw new RuntimeException('Invalid file accepted'); }
catch (InvalidArgumentException $expected) {}
$currentBook = readBodMnjWorkbook(2026, '2026-06');
$priorBook = readBodMnjWorkbook(2025, '2025-12');
$current = getBodMnjSheet($currentBook);
$prior = getBodMnjSheet($priorBook);
$book = buildBodMnjWorkbook(2026, '2026-06');
$sheet = $book->getSheetByName('BOD MNJ');
$offset = getBodMnjLastDataRow($current) + 3;
checkBod($offset === 56, 'Prior table must start at row 57');
checkBod($book->getSheetCount() === 1, 'Expected a single combined sheet');
compareBodBlock($current, $sheet, 0);
compareBodBlock($prior, $sheet, $offset);
$oldBook = buildBodMnjWorkbook(2025, '2025-06');
compareBodBlock($prior, $oldBook->getSheetByName('BOD MNJ'), 0);
$temp = tempnam(sys_get_temp_dir(), 'bod-test-');
try {
    foreach (['Ods', 'Xlsx'] as $kind) {
        // Writers may populate hyperlink/style caches; isolate each export.
        $exportBook = clone $book;
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($exportBook, $kind);
        $writer->setPreCalculateFormulas(false);
        $writer->save($temp);
        $roundtrip = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp);
        compareBodBlock($current, $roundtrip->getSheet(0), 0, false);
        compareBodBlock($prior, $roundtrip->getSheet(0), $offset, false);
        $roundtrip->disconnectWorksheets();
        $exportBook->disconnectWorksheets();
    }
    // Transferring into the full report must keep the parent/style indices valid.
    $report = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $report->getSheet(0)->setTitle('LAPORAN');
    $report->getSheet(0)->setCellValue('A1', 'unchanged');
    $report->addExternalSheet($sheet);
    checkBod($report->getSheet(0)->getCell('A1')->getValue() === 'unchanged', 'Other sheet changed');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($report))->save($temp);
    compareBodBlock($current, $report->getSheetByName('BOD MNJ'), 0);
} finally { unlink($temp); }

$conn = new BodTestConnection();
saveBodMnjRecord($conn, '2025-06', base64_encode($bytes[2025]));
saveBodMnjRecord($conn, '2026-06', base64_encode($bytes[2026]));
checkBod(findBodMnjRecord($conn, '2026-05') === null, 'Future upload leaked into earlier month');
checkBod(findBodMnjRecord($conn, '2025-12')['period'] === '2025-06', 'Prior-year latest upload not selected');
checkBod(findBodMnjRecord($conn, '2026-12')['period'] === '2026-06', 'Current-year latest upload not selected');
$uploadedCurrent = decodeBodMnjWorkbook(base64_encode($bytes[2026]), 2026);
$uploadedCurrent->getSheet(0)->setCellValue('D22', 123456);
$temp = tempnam(sys_get_temp_dir(), 'bod-upload-');
try {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($uploadedCurrent);
    $writer->setPreCalculateFormulas(false);
    $writer->save($temp);
    $upload = base64_encode(file_get_contents($temp));
    saveBodMnjRecord($conn, '2026-06', $upload);
    checkBod(count($conn->records) === 2, 'Replacing current year erased prior year');
    checkBod(findBodMnjRecord($conn, '2026-06')['base64'] === $upload, 'Original uploaded binary changed');
    $updated = buildBodMnjWorkbook(2026, '2026-06', $conn)->getSheet(0);
    checkBod($updated->getCell('D22')->getValue() === 123456, 'Upload did not override default');
    compareBodBlock($prior, $updated, $offset);
} finally { unlink($temp); }
if (isset($argv[1])) {
    foreach (['ods', 'xlsx'] as $kind) {
        $browserBook = \PhpOffice\PhpSpreadsheet\IOFactory::load($argv[1] . '/bod-2026.' . $kind);
        compareBodBlock($current, $browserBook->getSheetByName('BOD MNJ'), 0, false, true);
        compareBodBlock($prior, $browserBook->getSheetByName('BOD MNJ'), $offset, false, true);
    }
}
// Cutoffs, ratios, finance summary and previous-year comparison all follow the month.
foreach ([1, 4, 6, 12] as $month) {
    $period = '2026-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
    $checkBook = buildBodMnjWorkbook(2026, $period);
    $checkSheet = $checkBook->getSheetByName('BOD MNJ');
    foreach ([22, 37, 51, 52] as $row) {
        $expected = 0;
        for ($column = 4; $column < 4 + $month; $column++) {
            $amount = $current->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row)->getOldCalculatedValue();
            if ($amount === null) $amount = $current->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row)->getValue();
            if (is_numeric($amount)) $expected += $amount;
        }
        $actual = $checkSheet->getCell('C' . $row)->getCalculatedValue();
        checkBod(is_numeric($actual) && abs($actual - $expected) < 0.0001, 'YTD cutoff mismatch: ' . $period . ' C' . $row);
    }
    checkBod($checkSheet->getCell('C5')->getCalculatedValue() === $checkSheet->getCell('C22')->getCalculatedValue(), 'Summary sales out of sync');
    checkBod($checkSheet->getCell('D5')->getCalculatedValue() === $checkSheet->getCell('C37')->getCalculatedValue(), 'Summary promotion out of sync');
    checkBod($checkSheet->getCell('C59')->getCalculatedValue() === $checkSheet->getCell('C20')->getCalculatedValue(), 'Prior-year cutoff differs');
    checkBod($checkSheet->getCell('C61')->getCalculatedValue() === '', 'Missing previous-year data became zero');
    $ratio = $checkSheet->getCell('C52')->getCalculatedValue() / $checkSheet->getCell('C51')->getCalculatedValue();
    checkBod(abs($checkSheet->getCell('C53')->getCalculatedValue() - $ratio) < 1e-12, 'YTD ratio mismatch');
    checkBod(abs($checkSheet->getCell('J22')->getCalculatedValue()) < 0.0001, 'Circular or changed July residual');
}
$priorFixture = readBodMnjWorkbook(2025, '2025-12');
for ($i = 0; $i < 12; $i++) $priorFixture->getSheet(0)->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 4) . '5', $i + 1);
populateBodMnjYtd($priorFixture->getSheet(0), '2025-04');
checkBod($priorFixture->getSheet(0)->getCell('C5')->getCalculatedValue() === 10, 'Previous-year monthly cutoff mismatch');

echo 'PASS: server values, formulas, cell styles, ODS/XLSX roundtrip, upload storage/selection, replacement, other sheets and browser exports.', PHP_EOL;
