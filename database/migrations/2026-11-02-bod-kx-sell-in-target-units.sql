-- Jalankan pada tabel target yang sudah ada, jika kolom units belum tersedia.
ALTER TABLE `BOD KX SELL IN target`
    ADD COLUMN units DECIMAL(24,6) NULL;
