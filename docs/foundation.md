# Fondasi AMS M01

Issue M01 mencakup fondasi `I18N-001` dan `OPS-001`. Halaman publik tersedia di `/` dan pemeriksaan proses Laravel di `/up`. Shell internal dapat diuji dengan data sintetis pada `/preview` di environment lokal dan pengujian. [Issue #1](https://github.com/surega02/asset-management-system/issues/1)

## Menjalankan aplikasi

Gunakan PHP 8.3 atau lebih baru dengan ekstensi PDO MySQL dan mbstring, Composer, Node.js 24, dan MySQL 8. Salin `.env.example` ke `.env` pada checkout baru. Isi kredensial lokal untuk skema `ams` yang dibuat sebelumnya dan jalankan dari akar proyek.

```powershell
composer install
npm ci
npm run build
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1
```

Jalankan worker lokal di terminal lain dengan `php artisan queue:work database --tries=3 --timeout=60`. Waktu `--timeout` harus lebih pendek dari `DB_QUEUE_RETRY_AFTER` yang bawaan nilainya 90 detik. Efek antrean dijadwalkan setelah transaksi berhasil commit. `/up` memeriksa proses aplikasi. Koneksi MySQL dan pemrosesan antrean diperiksa terpisah oleh suite pengujian.

## Pengujian dan alur Git

Kerjakan perubahan di branch fitur dan buka pull request ke `main`. Workflow CI membangun aset, memeriksa format PHP, menjalankan migrasi pada MySQL 8.4 sementara, dan menjalankan PHPUnit. [Workflow CI](../.github/workflows/ci.yml)

Untuk menjalankan suite penuh secara lokal, siapkan MySQL dan skema khusus yang namanya berakhiran `_test`, seperti `ams_m01_test`. Gunakan akun dengan akses ke skema tersebut dan arahkan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di environment proses. Jalankan perintah berikut setelah memeriksa target database.

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:QUEUE_CONNECTION = 'database'
$env:AMS_TEST_MYSQL = '1'
php artisan migrate
php vendor/bin/phpunit
php vendor/bin/pint --test
npm run build
```

Suite MySQL memeriksa bahwa antrean menunggu commit, membuang job saat rollback, dan diproses worker tanpa masuk `failed_jobs`. Migrasi M01 membuat `jobs`, `job_batches`, dan `failed_jobs`. Rollback migrasi menghapus tiga tabel beserta isinya, sehingga pemulihan skema yang telah digunakan memerlukan backup atau pengosongan antrean terlebih dahulu.

## Pola untuk modul berikutnya

- **Actions dan transaksi.** Panggil Action dari HTTP, Livewire, impor, atau worker. Gunakan `DB::transaction` untuk perubahan domain, histori, dan audit. Kunci row stabil sebelum membaca invariant yang dapat berubah, pakai urutan penguncian konsisten, dan batasi percobaan ulang deadlock. Queue bawaan menunda pengiriman hingga transaksi berhasil.
- **Policy dan Query Scope.** Pusatkan ekspansi scope pada query per modul dan gunakan query yang sama untuk daftar, ringkasan, laporan, dan ekspor. Panggil `Gate::forUser($actor)->authorize(...)` dalam Action dan periksa scope sumber maupun tujuan di transaksi ketika sumber daya bergerak. Worker memuat ulang aktor dan memeriksa akses saat eksekusi. Pada aset, scope operasional mengikuti cabang lokasi fisik dan pengecualian baca personal mengikuti penugasan langsung.
- **Audit logger.** Buat penulis append-only ketika FK pengguna dan aset tersedia. Action meneruskan aktor, subjek, event, alasan, dan snapshot sebelum/sesudah yang dirakit dari field bisnis yang diizinkan. Tulis dalam transaksi domain yang sama. Jangan teruskan request mentah, kredensial, token, rahasia 2FA, atau isi lampiran. Log exception operasional memakai exception handler Laravel dan terpisah dari audit bisnis.
- **Penanganan kesalahan.** Gunakan validasi Laravel untuk input, Policy untuk penolakan akses, dan exception handler bawaan untuk respons HTTP. Biarkan kegagalan membatalkan transaksi dan aktifkan detail debug hanya saat perlu di lingkungan pengembangan. Lokalkan pesan antarmuka yang ditampilkan kepada pengguna.

## Pemeriksaan desain

Audit M01 memakai `PRODUCT.md` dan `DESIGN.md` yang sudah disetujui. Shell publik memakai topbar, kontrol bahasa dan tema, serta panel Blade dari sistem yang sama. Shell dashboard tetap menempatkan aksi lapangan sebelum pita cabang. Bidang kode membedakan validasi, sedang mencari, hasil kosong, dan hasil ditemukan. Pemilihan locale dashboard berlangsung dalam komponen yang sama, dan pilihan tema disimpan di browser. State kerja lintas muat ulang dashboard berada di luar kebutuhan perpindahan bahasa M01.

Perbedaan terhadap dokumentasi desain saat ini adalah layout publik yang tidak memakai sidebar dan menggunakan tombol konfirmasi bahasa. Di dashboard, locale memperbarui shell secara langsung melalui Livewire. [Keputusan teknis M01](adr/0001-foundation-boundaries.md)

## Bukti verifikasi M01

- PHP 8.4.5, Laravel 13.40.0, Livewire 4.4.8, dan MySQL 8.0.30 digunakan pada pengujian lokal. Migrasi awal, rollback satu migrasi, dan migrasi ulang berhasil pada skema uji terisolasi.
- `php vendor/bin/phpunit` dengan MySQL aktif lulus 6 tes dan 72 assertion. `php vendor/bin/pint --test` dan `npm run build` juga lulus.
- Browser memverifikasi desktop 1440 px dan ponsel 390 px, tema terang dan gelap, ketiga bahasa, navigasi papan ketik, pemulihan pencarian antar cabang, dan fokus kolom kode dari panel QR.
- Impeccable critique memakai dua penilaian independen. Detektor mengembalikan nol temuan dari target Blade, dengan cakupan parsing yang tidak dirinci oleh CLI. Pemeriksaan browser memverifikasi perbaikan bahasa pesan validasi, pemulihan kode, label pencarian terakhir, dan fokus menu.
