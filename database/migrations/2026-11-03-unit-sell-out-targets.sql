CREATE TABLE IF NOT EXISTS `target unit sell out` (
    report_year SMALLINT UNSIGNED NOT NULL,
    product VARCHAR(30) NOT NULL,
    kind VARCHAR(12) NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    units DECIMAL(24,6) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_year, product, kind, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
