<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Prefer saved historical snapshots; a later same-year upload can supply earlier months when no snapshot exists. */
function loadMtmTemplateHistory(mysqli $conn, string $period, bool $resetJanuary = true): array
{
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $period, $parts)) {
        throw new InvalidArgumentException('Periode template MTM tidak valid.');
    }
    $year = $parts[1];
    try {
        $stmt = $conn->prepare('SELECT report_period,metric_code,value_type,month_number,amount
            FROM mtm_monthly_values
            WHERE report_period = (
                SELECT COALESCE(MAX(CASE WHEN report_period <= ? THEN report_period END),MIN(report_period)) FROM mtm_monthly_values
                WHERE report_period LIKE CONCAT(?, "-%")
                  AND metric_code IN ("REVENUE","EXISTING","NEW_PRODUCT")
                  AND value_type IN ("TARGET","ROLLING","ACTUAL")
            ) AND metric_code IN ("REVENUE","EXISTING","NEW_PRODUCT")
              AND value_type IN ("TARGET","ROLLING","ACTUAL")');
        $stmt->bind_param('ss', $period, $year);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_values(array_filter($rows,static fn($r)=>
            (!$resetJanuary || $parts[2]!=='01' || $r['value_type']==='TARGET')
            && ($r['value_type']==='TARGET' || $r['report_period']<=$period || (int)$r['month_number']<=(int)$parts[2])));
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() === 1146) return []; // First upload: no MTM table yet.
        throw $error;
    }
}

/** Copy editable values only, keeping Revenue formulas and the new period's labels. */
function fillMtmTemplateHistory(Spreadsheet $book, string $period, array $records): ?string
{
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $period, $parts)) {
        throw new InvalidArgumentException('Periode template MTM tidak valid.');
    }
    $sheet = $book->getSheetByName('MTM');
    if (!$sheet) return null;
    $allowedTypes = ['TARGET','ROLLING','ACTUAL'];
    $allowedMetrics = ['REVENUE','EXISTING','NEW_PRODUCT'];
    $eligible = array_filter($records, static fn($r) =>
        preg_match('/^'.preg_quote($parts[1],'/').'-(0[1-9]|1[0-2])$/D', (string)($r['report_period'] ?? ''))
        && in_array($r['metric_code'] ?? '', $allowedMetrics, true)
        && in_array($r['value_type'] ?? '', $allowedTypes, true));
    if (!$eligible) return null;
    $past = array_filter($eligible,static fn($r)=>$r['report_period']<=$period);
    $source = $past ? max(array_column($past,'report_period')) : min(array_column($eligible,'report_period'));
    $rowMap = ['EXISTING'=>['TARGET'=>10,'ROLLING'=>11,'ACTUAL'=>12], 'NEW_PRODUCT'=>['TARGET'=>14,'ROLLING'=>15,'ACTUAL'=>16]];
    foreach ($eligible as $record) {
        if ($record['report_period'] !== $source) continue;
        $row = $rowMap[$record['metric_code']][$record['value_type']] ?? null;
        $month = (int)($record['month_number'] ?? 0);
        $value = $record['amount'] ?? null;
        if ($record['value_type']!=='TARGET' && ($parts[2]==='01' || ($source>$period && $month>(int)$parts[2]))) continue;
        if ($row === null || $month < 1 || $month > 12 || $value === null || $value === '') continue;
        if (!is_numeric($value) || !is_finite((float)$value)) throw new RuntimeException('Nilai MTM tersimpan tidak valid.');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($month+2).$row, (float)$value);
    }
    $book->getSheetByName('_TEMPLATE')?->setCellValue('A6',$source);
    $sheet->setCellValue('A3','Melanjutkan data tersimpan '.$source.'. Isi sel kuning per bulan; Revenue dihitung otomatis dari Existing + New Product.');
    return $source;
}
