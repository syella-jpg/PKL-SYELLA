<?php

/** Ensure the two report tables used by upload and download endpoints exist. */
function ensureSalesReportTables(mysqli $conn): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS `revenue_selling_out` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NULL,
            gb VARCHAR(50) NULL,
            product_code VARCHAR(100) NULL,
            principal_product_code VARCHAR(100) NULL,
            product_name VARCHAR(255) NULL,
            kategori VARCHAR(100) NULL,
            qty_jan DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_feb DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_mar DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_apr DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_mei DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_jun DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_jul DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_agt DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_sep DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_okt DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_nov DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_des DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            rev_jan DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_feb DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_mar DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_apr DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_mei DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_jun DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_jul DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_agt DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_sep DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_okt DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_nov DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_des DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            KEY idx_revenue_selling_out_period (report_period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS `revenue_selling_in` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NOT NULL,
            parameter VARCHAR(100) NOT NULL,
            achievement DECIMAL(20,2) NOT NULL DEFAULT 0,
            UNIQUE KEY unique_revenue_selling_in_period_parameter (report_period, parameter)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $statement) {
        if (!$conn->query($statement)) {
            throw new RuntimeException('Gagal menyiapkan tabel laporan Revenue: ' . $conn->error);
        }
    }
}
