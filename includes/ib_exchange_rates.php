<?php
/** Kurs Rupiah per 1 USD, tersimpan terpisah untuk setiap tahun laporan IB. */
function validateIbExchangeRate($value): float
{
    if (!is_scalar($value) || !is_numeric($value)) {
        throw new InvalidArgumentException('Isi kurs IB dengan angka, contoh 16700 (tanpa pemisah ribuan).');
    }
    $rate = (float)$value;
    if (!is_finite($rate) || $rate <= 0 || $rate > 1000000000) {
        throw new InvalidArgumentException('Kurs IB harus lebih dari 0 dan maksimal 1000000000.');
    }
    return $rate;
}
function ensureIbExchangeRates(mysqli $conn): void
{
    if (!$conn->query('CREATE TABLE IF NOT EXISTS ib_exchange_rates (
        report_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
        usd_rate DECIMAL(18,6) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )')) {
        throw new RuntimeException('Gagal menyiapkan penyimpanan kurs IB.');
    }
}
