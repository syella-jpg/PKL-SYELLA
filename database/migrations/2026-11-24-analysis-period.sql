-- Keep old analyses without a known report period intact (NULL).
-- Do not infer the report month from the date the note was written.
SET @analysis_period_sql = (
    SELECT IF(COUNT(*) = 0,
      'ALTER TABLE report_analysis ADD COLUMN report_period CHAR(7) NULL, ADD INDEX idx_analysis_period(report_period,report_name,tab_name)',
      'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'report_analysis' AND COLUMN_NAME = 'report_period'
);
PREPARE analysis_period_migration FROM @analysis_period_sql;
EXECUTE analysis_period_migration;
DEALLOCATE PREPARE analysis_period_migration;
