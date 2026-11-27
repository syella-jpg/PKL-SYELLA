-- Jalankan pada database aplikasi yang sudah digunakan.
-- Penyimpanan file sumber NEAR ED per periode (YYYY-MM).
-- Nama tabel menggunakan spasi sesuai permintaan; selalu gunakan backtick.
CREATE TABLE IF NOT EXISTS `near ED` (
    report_period CHAR(7) NOT NULL PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    base64 LONGTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
