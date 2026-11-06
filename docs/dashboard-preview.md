# Pratinjau shell dan dashboard AMS

Pratinjau lokal menampilkan alur Petugas Aset dengan data sintetis. Registrasi dan pemindaian membuka panel penjelasan. Pencarian kode dan pemilihan cabang dapat dicoba langsung. Bahasa disimpan dalam sesi dan tema disimpan di browser.

| Persyaratan | Cakupan pratinjau |
| --- | --- |
| RPT-001 | Ringkasan, lokasi fisik, dan aktivitas dari satu kumpulan contoh per cabang. |
| I18N-001 | Antarmuka Indonesia, Inggris, dan Tionghoa Sederhana, dua tema, tata letak responsif. |
| AST-001 dan QR-001 | Posisi dan pengungkapan aksi registrasi serta pemindaian, dengan pencarian manual pada data contoh. |
| IAM-003 | Pratinjau tersedia di lingkungan lokal dan pengujian. Filter cabang di sini hanya memisahkan data sintetis. |

Otorisasi berbasis Policy dan cakupan organisasi, basis data, registrasi, kamera, dan QR publik merupakan pekerjaan implementasi berikutnya. Pratinjau tidak memerlukan migrasi.

## Menjalankan di PowerShell

Gunakan PHP 8.3 atau lebih baru, Composer, serta Node.js dan npm. Jalankan dari akar proyek.

```powershell
composer install
npm ci
npm run build
$env:APP_ENV = 'local'
$env:APP_KEY = php artisan key:generate --show
php artisan serve --host=127.0.0.1 --port=8000
```

Buka halaman publik di `/` atau langsung ke [pratinjau dashboard](http://127.0.0.1:8000/preview) pada environment lokal. Untuk contoh Jakarta gunakan `AST-000128`, lalu pilih Bandung dan coba `AST-000127`. Ganti bahasa saat hasil pencarian terbuka untuk memeriksa bahwa cabang, isian, dan hasil tetap tersedia.

```powershell
php vendor/bin/phpunit
php vendor/bin/pint --test
```

Pengujian koneksi dan antrean MySQL memerlukan skema uji khusus. [Panduan M01](foundation.md)
