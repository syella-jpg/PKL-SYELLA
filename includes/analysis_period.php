<?php
function validateAnalysisPeriod($period): string {
    if(!is_string($period)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period))throw new InvalidArgumentException('Pilih periode analisis yang valid (YYYY-MM).');
    return $period;
}
function ensureAnalysisPeriodColumn(mysqli $conn): void {
    $result=$conn->query("SHOW COLUMNS FROM report_analysis LIKE 'report_period'");$missing=$result->num_rows===0;$result->free();
    if($missing){try{$conn->query('ALTER TABLE report_analysis ADD COLUMN report_period CHAR(7) NULL, ADD INDEX idx_analysis_period(report_period,report_name,tab_name)');}catch(mysqli_sql_exception $error){if((int)$error->getCode()!==1060)throw $error;}}
}
