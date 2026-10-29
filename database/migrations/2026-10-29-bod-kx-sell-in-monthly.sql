CREATE TABLE IF NOT EXISTS bod_kx_sell_in_monthly (
    report_period CHAR(7) NOT NULL,
    product VARCHAR(30) NOT NULL,
    units DECIMAL(24,6) NULL,
    rupiah DECIMAL(24,6) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_period, product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
