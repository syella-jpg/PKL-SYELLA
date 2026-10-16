<?php

/** Ensure the two report tables used by upload and download endpoints exist. */
function ensureSalesReportTables(mysqli $conn): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS `revenue_selling_out` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NULL,
            gb VARCHAR(50) NULL,
            product_code VARCHAR(100) NULL,
            principal_product_code VARCHAR(100) NULL,
            product_name VARCHAR(255) NULL,
            kategori VARCHAR(100) NULL,
            qty_jan DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_feb DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_mar DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_apr DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_mei DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_jun DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_jul DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_agt DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_sep DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_okt DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_nov DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_des DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            rev_jan DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_feb DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_mar DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_apr DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_mei DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_jun DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_jul DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_agt DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_sep DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_okt DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_nov DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_des DECIMAL(18,2) NOT NULL DEFAULT 0,
            rev_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            KEY idx_revenue_selling_out_period (report_period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS `revenue_selling_in` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            report_period CHAR(7) NOT NULL,
            parameter VARCHAR(100) NOT NULL,
            achievement DECIMAL(20,2) NOT NULL DEFAULT 0,
            UNIQUE KEY unique_revenue_selling_in_period_parameter (report_period, parameter)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $statement) {
        if (!$conn->query($statement)) {
            throw new RuntimeException('Gagal menyiapkan tabel laporan Revenue: ' . $conn->error);
        }
    }
}

/** TiDB cannot retrofit AUTO_INCREMENT on some legacy tables; allocate IDs safely. */
function ensureLegacyIdAllocator(mysqli $conn, string $table): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Nama tabel ID tidak valid.');
    }
    if (!$conn->query("CREATE TABLE IF NOT EXISTS `app_id_sequences` (
        table_name VARCHAR(64) NOT NULL PRIMARY KEY,
        next_id BIGINT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        throw new RuntimeException('Gagal menyiapkan penghitung ID: ' . $conn->error);
    }
    $column = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'");
    if (!$column || $column->num_rows === 0) {
        throw new RuntimeException('Tabel ' . $table . ' belum memiliki kolom id.');
    }
    $seed = $conn->prepare("INSERT IGNORE INTO app_id_sequences (table_name, next_id)
        SELECT ?, COALESCE(MAX(id), 0) + 1 FROM `{$table}`");
    if (!$seed) throw new RuntimeException('Gagal menyiapkan penghitung ID ' . $table . ': ' . $conn->error);
    $seed->bind_param('s', $table);
    if (!$seed->execute()) throw new RuntimeException('Gagal menginisialisasi ID ' . $table . ': ' . $seed->error);
    $seed->close();
}

function nextLegacyId(mysqli $conn, string $table): int
{
    $update = $conn->prepare('UPDATE app_id_sequences SET next_id = next_id + 1 WHERE table_name = ?');
    if (!$update) throw new RuntimeException('Gagal menyiapkan alokasi ID ' . $table . ': ' . $conn->error);
    $update->bind_param('s', $table);
    if (!$update->execute() || $update->affected_rows !== 1) {
        $error = $update->error ?: 'penghitung ID tidak ditemukan';
        $update->close();
        throw new RuntimeException('Gagal mengalokasikan ID ' . $table . ': ' . $error);
    }
    $update->close();
    $select = $conn->prepare('SELECT next_id - 1 FROM app_id_sequences WHERE table_name = ?');
    if (!$select) throw new RuntimeException('Gagal membaca ID ' . $table . ': ' . $conn->error);
    $select->bind_param('s', $table);
    if (!$select->execute()) throw new RuntimeException('Gagal membaca ID ' . $table . ': ' . $select->error);
    $select->bind_result($id);
    $select->fetch();
    $select->close();
    return (int)$id;
}
