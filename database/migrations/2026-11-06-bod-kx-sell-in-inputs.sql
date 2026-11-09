-- Product detail for the combined PENJUALAN + ROD input template.
-- Aggregates continue to use bod_kx_sell_in_monthly and `BOD KX SELL IN target`.
CREATE TABLE IF NOT EXISTS `BOD KX SELL IN TEMPLATE` (
    report_year SMALLINT UNSIGNED NOT NULL,
    report_period CHAR(7) NOT NULL,
    payload LONGBLOB NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
