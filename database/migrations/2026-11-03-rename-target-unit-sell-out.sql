-- Jalankan hanya jika tabel unit_sell_out_targets sudah ada
-- dan tabel target unit sell out belum ada. Data lama tetap dipertahankan.
RENAME TABLE `unit_sell_out_targets` TO `target unit sell out`;
