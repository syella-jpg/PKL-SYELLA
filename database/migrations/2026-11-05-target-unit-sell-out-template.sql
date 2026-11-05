-- Canonical monthly targets remain in `target unit sell out`.
-- Editable category/IB cumulative inputs are retained separately for template reuse.
CREATE TABLE IF NOT EXISTS unit_sell_out_target_inputs (
    report_year SMALLINT UNSIGNED NOT NULL,
    product VARCHAR(30) NOT NULL,
    payload MEDIUMBLOB NOT NULL,
    source_period CHAR(7) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (report_year, product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
