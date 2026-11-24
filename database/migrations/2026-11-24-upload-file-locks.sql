CREATE TABLE IF NOT EXISTS report_upload_sessions (
    token_hash CHAR(64) NOT NULL PRIMARY KEY,
    username VARCHAR(150) NOT NULL,
    expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS report_upload_files (
    section VARCHAR(100) NOT NULL,
    report_period CHAR(7) NOT NULL,
    has_upload TINYINT(1) NOT NULL DEFAULT 0,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    filename VARCHAR(255) NULL,
    uploaded_by VARCHAR(150) NULL,
    uploaded_at DATETIME NULL,
    unlocked_by VARCHAR(150) NULL,
    unlocked_at DATETIME NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY(section, report_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS report_upload_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(100) NOT NULL,
    report_period CHAR(7) NOT NULL,
    action VARCHAR(20) NOT NULL,
    actor VARCHAR(150) NOT NULL,
    filename VARCHAR(255) NULL,
    happened_at DATETIME NOT NULL,
    INDEX upload_event_period(report_period, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
