-- Tabel canonical IB lintas tahun. Sheet YFR - IBFM dan IBFM disimpan
-- dengan business_unit IB_FM oleh pemetaan upload.
CREATE TABLE IF NOT EXISTS `ib_monthly_values` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_period` CHAR(7) NOT NULL COMMENT 'Periode YYYY-MM',
    `month_number` TINYINT UNSIGNED NOT NULL COMMENT 'Bulan 1 sampai 12',
    `metric_code` VARCHAR(32) NOT NULL COMMENT 'revenue_exist, revenue_new, mcm',
    `business_unit` VARCHAR(16) NOT NULL COMMENT 'IB_FM, IB_FD, GROUP',
    `value_type` VARCHAR(8) NOT NULL COMMENT 'TARGET atau ACTUAL',
    `amount` DECIMAL(20,2) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ib_value` (`report_period`, `month_number`, `metric_code`, `business_unit`, `value_type`),
    KEY `idx_ib_period_metric` (`report_period`, `metric_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
