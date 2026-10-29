CREATE TABLE IF NOT EXISTS tm_bod_annual_templates (
    report_year SMALLINT UNSIGNED PRIMARY KEY,
    draft_json LONGTEXT NULL,
    active_json LONGTEXT NULL,
    version INT NOT NULL DEFAULT 0,
    updated_by VARCHAR(150) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tm_bod_template_versions (
    report_year SMALLINT UNSIGNED NOT NULL,
    version INT NOT NULL,
    template_json LONGTEXT NOT NULL,
    activated_by VARCHAR(150) NOT NULL,
    activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_year, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The runtime adds destination_ids to older transfer tables if missing.
-- Run this only if the column is absent and the API cannot ALTER the table:
-- ALTER TABLE tm_bod_analysis_transfers ADD COLUMN destination_ids MEDIUMTEXT NULL;
