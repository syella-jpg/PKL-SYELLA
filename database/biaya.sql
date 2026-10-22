-- Jalankan di SQL Editor TiDB. Data diisi melalui halaman Upload aplikasi.
USE laporan_korporat;

CREATE TABLE IF NOT EXISTS biaya_files (
    report_period CHAR(7) PRIMARY KEY,
    payload LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
