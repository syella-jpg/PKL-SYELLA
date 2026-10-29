CREATE TABLE IF NOT EXISTS tm_bod_design_uploads (
    id CHAR(32) PRIMARY KEY,
    report_year SMALLINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    pages_json LONGTEXT NOT NULL,
    uploaded_by VARCHAR(150) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tm_design_year (report_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
