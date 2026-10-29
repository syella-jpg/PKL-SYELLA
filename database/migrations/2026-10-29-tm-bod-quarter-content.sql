CREATE TABLE IF NOT EXISTS tm_bod_quarter_content (
    report_year SMALLINT UNSIGNED NOT NULL,
    quarter CHAR(2) NOT NULL,
    content_json LONGTEXT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_by VARCHAR(150) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_year, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
