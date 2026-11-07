# M12 · Kesiapan rilis MVP

Status kandidat **In Review**. Referensi OPS-001, IAM-001/002/003, I18N-001 dan AUD-001, dengan seluruh persyaratan P1 tetap mengikuti baseline Approved. M12 tidak memerlukan migrasi baru. Dokumen ini mencatat prosedur, bukti QA dan persetujuan yang masih diperlukan.

## Quality gate

CI `verify` menggunakan PHP 8.3 dan MySQL 8.4 nyata, migrasi dari database kosong, seluruh Feature tests, Pint, frontend build dan pengujian scanner. `ReleaseLoadTest` dijalankan terpisah pada database sintetis dengan akhiran `_test`, dalam transaksi yang dibatalkan setelah pengukuran. Data awal 8.000 unit pada dua cabang, ditambah 1.000 unit melalui parser dan worker impor yang sebenarnya. Kode dan assignment tiap unit tetap unik. Tiga sampel request hangat mencatat median/maksimum waktu serta jumlah query dashboard, daftar dan laporan. EXPLAIN mencatat pilihan indeks optimizer. Angka ini menggambarkan satu runner CI dan bukan kapasitas server produksi.

| Skenario baseline | Bukti otomatis |
|---|---|
| T-001, T-003 | PeopleConcurrencyTest, counter terkunci dan assignment serentak |
| T-002, T-015 | AssetLifecycleTest, registrasi dan rollback |
| T-006, T-007 | AssetTrackingTest, scope cabang fisik dan penugasan langsung |
| T-004, T-005 | AssetLifecycleTest |
| T-008 | PeopleWorkflowsTest dan PeopleConcurrencyTest |
| T-009, T-010 | AssetImportTest dan konkurensi impor |
| T-011 | AssetQrLabelTest dan scanner frontend |
| T-012, T-013 | AssetReportTest |
| T-014 | IdentityTest |

Nama file dan hasil aktual harus dicocokkan dengan log CI sebelum sign-off. Test konkurensi menjalankan proses terpisah pada MySQL, termasuk request serentak, pengiriman job ganda, lease kedaluwarsa dan pencabutan scope. Transaksi aplikasi menggunakan bounded retry tiga kali. Log pengujian harus membedakan penolakan domain yang benar dari kegagalan infrastruktur.

## Keamanan

Audit sumber berfokus pada `app/`, didukung konfigurasi, rute, Blade dan tests. Jalur yang ditelaah meliputi detail/lampiran/label lintas scope, QR anonim, impor serta ekspor CSV/XLSX, login, 2FA, notifikasi dan audit. Ini merupakan review terfokus dan tidak menyatakan seluruh deployment telah diaudit.

Parser CSV sekarang membatasi panjang logical record sebelum membuat array sel. Lebar berlebih menghasilkan baris invalid. XLSX membatasi ukuran setiap sel dan jumlah byte setelah dereferensi shared strings. Batas tambahan 1 MiB per CSV record dan 12 MiB total decoded XLSX menolak representasi yang berisiko walaupun ZIP atau unggah masih di bawah 10 MiB. Format tetap mendukung quoted comma, quoted newline, escaped quote dan BOM. Regresi menguji payload sembilan juta delimiter dan shared-string expansion.

Login mempertahankan lima kegagalan per akun/IP dan menyatukan identifier akun yang tidak ditemukan dalam satu bucket per IP. Batas luar 30 request login per menit/IP mengurangi alokasi hash dan lock. Impor pegawai memiliki throttle 10 request/menit seperti impor aset. Pengaturan produksi menggunakan cookie Secure secara default, HttpOnly dan SameSite Lax. Cache/session volume harus hanya dapat diakses akun layanan. Gunakan satu node aplikasi pada konfigurasi volume lokal ini. Deployment multi-node memerlukan store session/cache bersama yang telah divalidasi.

CSV ditujukan untuk delimiter koma. Pembukaan dengan delimiter lain memerlukan pemeriksaan klien spreadsheet. XLSX menyimpan teks sebagai inline string. Ekspor mempertahankan perlindungan awalan formula. Lakukan UAT menggunakan aplikasi spreadsheet organisasi untuk memastikan perilaku pembukaan file yang sebenarnya.

## Deployment NGINX/PHP-FPM

`Dockerfile` membangun aset frontend dan image PHP-FPM tanpa dependency dev. `.dockerignore` memakai allowlist sehingga `.env`, unggahan, backup, Git dan fixture lokal tidak masuk build context. Image web hanya membawa aset publik. NGINX melayani direktori build, menolak dotfile/PHP langsung dan meneruskan request aplikasi ke front controller. Port Compose terikat loopback. Worker database queue dan scheduler dikelola restart policy container.

1. Pilih host Linux, Docker Compose dan MySQL 8.4/InnoDB yang telah diuji. Sediakan TLS pada reverse proxy di depan port loopback web. Publikasikan hanya HTTPS. Set `AMS_TRUSTED_PROXIES` ke daftar IP/CIDR proxy terpercaya dipisah koma, sesuai alamat sumber yang dilihat NGINX. Default tidak mempercayai proxy. Proxy harus menimpa X-Forwarded-For/Host/Port/Proto dari klien. Pastikan URL HTTPS, redirect dan cookie aman diuji pada domain sebenarnya.
2. Simpan env di luar checkout, mode 0600. Isi `APP_ENV=production`, `APP_DEBUG=false`, APP_KEY baru dari secret manager, APP_URL HTTPS, kredensial database terbatas, session file, cache file dan database queue. `SESSION_SECURE_COOKIE=true`. Simpan APP_KEY secara aman untuk dekripsi 2FA dan queue. Set `DB_QUEUE_RETRY_AFTER` lebih besar dari timeout worker 60 detik, default 90 detik. Jangan mengubah APP_KEY saat upgrade.
3. Set `AMS_ENV_FILE` ke file env dan `AMS_IMAGE_TAG` ke commit yang ditinjau. Jalankan `docker compose build php web`. Simpan image immutable di registry privat atau backup artifact on-prem. Jalankan `docker compose run --rm php php artisan migrate --seed --force` sebelum menyalakan worker. Gunakan `php artisan ams:bootstrap-admin` sesuai dokumentasi identitas untuk akun admin pertama, melalui terminal terpercaya.
4. Jalankan `docker compose up -d`. Periksa `/up`, login, label, lampiran privat, scheduler dan antrean. Periksa `docker compose exec web nginx -t` dan `docker compose exec php php artisan schedule:list`. Pantau failed jobs, outbox tertunda, lease impor, kapasitas disk, respons 5xx dan log. Jangan tampilkan bindings SQL atau konten unggahan dalam alarm. Set `LOG_CHANNEL=stderr` agar log aplikasi mengikuti rotasi container, maksimum tiga file 10 MiB per layanan. Forward log yang perlu disimpan ke kolektor operasi dan tetapkan retensinya sebelum go-live.
5. Untuk upgrade, simpan backup terverifikasi, aktifkan maintenance dan hentikan worker/scheduler secara tertib. Jalankan migrasi kompatibel, perbarui image dan jalankan smoke test, kemudian buka akses. Proses antrean perlu image baru dan restart. Rollback kode menggunakan tag image sebelumnya apabila skema kompatibel. Jika perlu pemulihan data, gunakan restore terisolasi dan persetujuan pemilik data sebelum pergantian database/storage. Jangan menjalankan migrate rollback tanpa meninjau dampak data.

## Backup dan pemulihan

`deploy/backup.sh` membutuhkan Bash GNU/Linux, mysql client yang kompatibel server, gzip, tar, flock dan GPG. Sediakan `MYSQL_DEFAULTS` berupa option file mode 0600, `AMS_DATABASE`, `AMS_STORAGE`, `AMS_BACKUP_DIR`, serta `AMS_BACKUP_PASSPHRASE_FILE` mode 0600. Gunakan akun backup dengan hak yang dibutuhkan mysqldump. Direktori staging dan backup hanya dapat dibaca operator backup. Jadwalkan **setiap hari** melalui systemd timer atau cron operator, misalnya `0 2 * * * /opt/ams/ops/run-backup`, dengan wrapper yang memuat variabel tanpa mencetak secret dan meneruskan exit status ke monitoring.

Backup menyimpan snapshot SQL transaksi InnoDB dan private storage dalam arsip AES-256 GPG. Jalankan dalam maintenance dengan worker/scheduler berhenti jika diperlukan konsistensi antara database dan file. Backup yang berhasil dipublikasikan dengan rename atomik sebelum file dengan pola AMS berumur lebih dari 30 hari dihapus. Kegagalan dump/enkripsi harus memicu alarm dan mempertahankan backup lama. Simpan salinan terenkripsi di media/host terpisah serta passphrase di secret manager terpisah, termasuk prosedur pemulihannya.

Untuk drill, siapkan database kosong dengan nama berakhiran `_restore`, `AMS_RESTORE_DATABASE` dan direktori baru `AMS_RESTORE_STORAGE`. Jalankan `bash deploy/restore-drill.sh <arsip>`. Script menolak database berisi tabel dan direktori yang sudah ada, memverifikasi dekripsi sebelum ekstraksi dan mengukur usia snapshot serta durasi restore. Periksa jumlah baris, assignment, audit dan unduh lampiran melalui aplikasi yang menggunakan salinan hasil restore. Target RPO 24 jam/RTO 8 jam harus diukur ulang di host dan volume produksi, termasuk deteksi insiden, penyediaan host, pemulihan secret, verifikasi aplikasi dan pergantian akses.

Workflow `Release drill` menjalankan `bash deploy/release-drill.sh` di lingkungan sintetis sekali pakai. Ia membangun image, memeriksa NGINX/HTTP/CSRF, menghentikan worker sebelum enqueue lalu menghidupkannya kembali, memproses job impor nyata, menguji retensi selektif, mengenkripsi dump/file, memulihkan ke database berbeda serta membandingkan hasil. Angka RPO/RTO yang dicetak script hanya mencakup lingkup drill tersebut. Workflow menghapus container dan volume drill miliknya setelah selesai.

## UAT dan keputusan rilis

Pemilik produk dan perwakilan operasional perlu mengisi tabel ini pada staging yang sesuai konfigurasi produksi. Bukti milestone terdahulu tersedia di `docs/evidence/`. Pemeriksaan pengembang dan CI menjadi masukan UAT.

| Persona | Alur yang diterima | id / en / zh-CN | Bukti, tanggal, penanggung jawab | Status |
|---|---|---|---|---|
| Petugas Aset | Registrasi, QR/manual search, pindah, reassign, lifecycle, impor parsial/retry | Menunggu | Menunggu | Pending |
| Admin | Master, pegawai, transfer, akun, scope, 2FA/reset, pengaturan | Menunggu | Menunggu | Pending |
| Pimpinan | Acting/definitif efektif, dashboard dan laporan sesuai scope | Menunggu | Menunggu | Pending |
| Karyawan | Aset saya lintas cabang, akses file sah, penolakan edit, notifikasi | Menunggu | Menunggu | Pending |
| Petugas ponsel | Kamera nyata HTTPS, izin ditolak, fallback, light/dark, keyboard | Menunggu | Menunggu | Pending |

Sebelum tag `v1.0.0`, catat URL CI yang lulus pada commit kandidat, hasil audit dan penyelesaian temuan, angka load/drill, checklist UAT lengkap, pemilik operasi, latihan restore staging, serta sign-off pemilik produk dengan tanggal dan commit. Catat juga masalah yang diterima beserta dampaknya. Penerbitan tag/release dan go-live menunggu persetujuan tersebut. Selama bukti atau sign-off belum lengkap, issue #23 berstatus In Review.
