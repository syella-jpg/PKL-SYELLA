CREATE TABLE IF NOT EXISTS tm_bod_director_notes (
    id CHAR(32) NOT NULL PRIMARY KEY,
    report_year SMALLINT UNSIGNED NOT NULL,
    quarter CHAR(2) NOT NULL,
    note_text TEXT NOT NULL,
    created_by VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_director_period (report_year, quarter, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
