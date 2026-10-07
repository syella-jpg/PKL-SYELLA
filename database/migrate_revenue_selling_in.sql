-- Jalankan pada database `LAPORAN KORPORAT` setelah memastikan backup tersedia.
-- Data lama disalin; baris yang sudah ada di tabel tujuan dipertahankan.
CREATE TABLE IF NOT EXISTS `revenue_selling_in` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    report_period CHAR(7) NOT NULL,
    parameter VARCHAR(100) NOT NULL,
    achievement DECIMAL(20,2) NOT NULL DEFAULT 0,
    UNIQUE KEY unique_period_parameter (report_period, parameter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `revenue_selling_in` (report_period, parameter, achievement)
SELECT report_period, parameter, achievement
FROM `revenue_konimex_selling_in`;

DROP TABLE `revenue_konimex_selling_in`;
