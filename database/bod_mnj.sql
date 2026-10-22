-- Run in TiDB SQL Editor.
USE laporan_korporat;

CREATE TABLE IF NOT EXISTS bod_mnj_files (
    report_period CHAR(7) PRIMARY KEY,
    payload LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
