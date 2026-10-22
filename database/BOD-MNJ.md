BOD MNJ — unggah file asli
=========================

Di halaman Upload aplikasi, pilih bagian BOD MNJ, periode, lalu file ODS atau XLSX.
Unggahan disimpan ke TiDB pada bod_mnj_files dan mengganti file periode yang sama.
Payload menyimpan base64 byte asli, filename dari file pilihan, dan SHA-256.
File tidak ditulis ulang sehingga tampilan asli dipertahankan.

Unduh melalui /api/report.php?endpoint=bod_mnj_download&period=2026-06
(ganti periode sesuai pilihan). Unduhan menggunakan nama asli dan format unggahan.

Pasang perubahan ke Vercel untuk mengaktifkan alur ini di situs produksi.
Koneksi memakai konfigurasi DB_* dan TLS pada includes/database.php.

bod_mnj.sql membuat struktur tabel. Seed SQL dan importer CLI adalah contoh impor
file lama, bukan langkah upload aplikasi. Data contoh yang sudah diimpor dapat
diganti dengan upload pada periode yang sama.
Alur laporan gabungan lama tetap membutuhkan sumber satu tahun; endpoint unduhan
asli mendukung file yang sudah berisi perbandingan beberapa tahun.
