-- Tambahkan periode pada tabel EDP yang sudah dibuat tanpa kolom periode.
ALTER TABLE `revenue_edp_mnj`
    ADD COLUMN IF NOT EXISTS `report_period` CHAR(7) NULL;

-- Gunakan hanya untuk data EDP lama yang memang berasal dari Desember 2025.
UPDATE `revenue_edp_mnj`
SET `report_period` = '2025-12'
WHERE `report_period` IS NULL;
