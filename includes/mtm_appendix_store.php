<?php
function ensureMtmAppendixStore(mysqli $conn):void {
    $conn->query("CREATE TABLE IF NOT EXISTS mtm_monthly_values (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_period CHAR(7) NOT NULL,metric_code VARCHAR(24) NOT NULL,value_type VARCHAR(8) NOT NULL,
        month_number TINYINT UNSIGNED NOT NULL,amount DECIMAL(20,2) NULL,
        UNIQUE KEY uq_mtm_period_metric_type_month (report_period,metric_code,value_type,month_number),
        KEY idx_mtm_period_metric (report_period,metric_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns=array_column($conn->query('SHOW COLUMNS FROM mtm_monthly_values')->fetch_all(MYSQLI_ASSOC),'Field');
    $missing=[];
    foreach(['appendix_name'=>'VARCHAR(255) NULL','appendix_data'=>'LONGBLOB NULL','appendix_sha256'=>'CHAR(64) NULL','appendix_updated_at'=>'DATETIME NULL'] as $name=>$type)if(!in_array($name,$columns,true))$missing[]='ADD COLUMN `'.$name.'` '.$type;
    if($missing)$conn->query('ALTER TABLE mtm_monthly_values '.implode(',',$missing));
    $exists=$conn->query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mtm_appendix_uploads'")->fetch_assoc()['n'];
    if($exists){
        // Keep the original table as a backup; the application reads/writes only mtm_monthly_values.
        $conn->query("INSERT INTO mtm_monthly_values (report_period,metric_code,value_type,month_number,amount,appendix_name,appendix_data,appendix_sha256,appendix_updated_at)
            SELECT CONCAT(old.report_year,'-01'),'APPENDIX','FILE',0,NULL,old.original_name,old.file_data,old.file_sha256,old.updated_at
            FROM mtm_appendix_uploads old
            LEFT JOIN mtm_monthly_values current ON current.report_period=CONCAT(old.report_year,'-01') AND current.metric_code='APPENDIX' AND current.value_type='FILE' AND current.month_number=0
            WHERE current.id IS NULL");
    }
}
