BIAYA — upload ke TiDB
======================

Jalankan biaya.sql jika tabel biaya_files belum ada. Tabel yang sudah ada tetap dipakai.
Tidak perlu memasukkan file contoh melalui SQL.

Pada halaman Upload aplikasi, pilih BIAYA, periode dengan tahun yang sesuai,
lalu pilih file ODS atau XLSX sendiri. Upload periode yang sama mengganti data
dan file periode tersebut.

Payload menyimpan target dan actual untuk laporan yang sudah berjalan, ditambah
base64 byte file asli, filename, dan sha256. Sumber 2025 memakai TARGET ROLLING.
File asli tidak ditulis ulang atau dikonversi.

Unduhan file asli:
 /api/report.php?endpoint=biaya_download&period=2026-10
Ganti periode dengan periode upload yang tepat. Nama file mengikuti unggahan.
Data lama tanpa byte asli perlu diunggah ulang untuk menggunakan unduhan ini.

Pasang kode terbaru di Vercel; konfigurasi DB_* harus menunjuk TiDB laporan_korporat.
