# Menjalankan Laporan Korporat di Vercel

Vercel menjalankan halaman HTML secara statis. Endpoint PHP aplikasi sekarang dipublikasikan sebagai Vercel Functions melalui `api/`, sementara file PHP di `dashboard/` dan `upload/` tetap menjadi implementasi bersama untuk pemakaian lokal.

## Database

Vercel tidak dapat terhubung ke MySQL `127.0.0.1` milik komputer lokal. Siapkan MySQL yang dapat diakses dari server Vercel dan buat environment variables berikut di **Project Settings → Environment Variables** untuk Production (serta Preview bila diperlukan):

- `DB_HOST`: hostname database
- `DB_USER`: username database
- `DB_PASSWORD`: password database
- `DB_NAME`: nama database, umumnya `LAPORAN KORPORAT`

Setelah mengisi variabel, redeploy project. Jangan memasukkan kredensial ke file frontend atau commit ke Git.

## Batas ukuran file

Vercel membatasi request ke Function sampai 4,5 MB. Endpoint PHP dapat menerima file yang lebih kecil; pilihan file sampai 50 MB tetap tersedia saat aplikasi dijalankan pada server PHP biasa. Agar unggahan di atas batas Vercel tetap tersedia, gunakan backend PHP yang menerima unggahan besar atau pindahkan transfer file ke object storage sebelum pemrosesan.
