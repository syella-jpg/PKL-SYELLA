CREATE TABLE IF NOT EXISTS `upb bj kx` (
    report_period CHAR(7) NOT NULL PRIMARY KEY,
    base64 LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preserve previous uploads, without replacing newer canonical records.
SET @upb_bj_copy_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.tables
           WHERE table_schema = DATABASE() AND table_name = 'upb_bj_input_files'),
    'INSERT IGNORE INTO `upb bj kx` (report_period, base64) SELECT report_period, base64 FROM upb_bj_input_files',
    'SELECT 1'
);
PREPARE upb_bj_copy_stmt FROM @upb_bj_copy_sql;
EXECUTE upb_bj_copy_stmt;
DEALLOCATE PREPARE upb_bj_copy_stmt;
