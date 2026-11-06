# Identitas dan akses AMS M02

M02 mencakup `IAM-001`, `IAM-002`, `IAM-003`, dan fondasi audit identitas `AUD-001`. Pengguna masuk melalui `/login`. Ruang kerja terautentikasi ada di `/workspace`, keamanan akun di `/security`, akun di `/admin/users`, dan katalog peran serta izin di `/admin/access`. [Issue #3](https://github.com/surega02/asset-management-system/issues/3)

## Migrasi dan akun pertama

1. Siapkan `APP_KEY`, URL aplikasi, dan koneksi MySQL sesuai panduan fondasi. Gunakan HTTPS dan `SESSION_SECURE_COOKIE=true` di lingkungan produksi. Sesi terenkripsi kedaluwarsa setelah 30 menit tanpa aktivitas.
2. Jalankan `php artisan migrate --seed` pada skema target yang sudah diperiksa. Seeder menambah peran dan izin bawaan secara idempoten. Akun awal memerlukan karyawan yang sudah ada.
3. Sebelum UI master tersedia, operator basis data memasukkan referensi cabang, divisi, departemen, lokasi, jabatan, dan karyawan pertama dari sumber yang telah diverifikasi. Gunakan kolom dan FK pada ERD. Jalankan `php artisan ams:bootstrap-admin <NIK>` sekali untuk akun Super Admin pertama.
4. Setelah memverifikasi identitas karyawan dan menyiapkan penyerahan privat, operator server menjalankan `php artisan ams:password-code <NIK>`. Karyawan memasukkan kode pada `/reset-password` untuk menetapkan kata sandinya. Perlakukan stdout dari perintah ini seperti kredensial sementara.
5. Untuk pengiriman reset melalui email, konfigurasi SMTP, alamat pengirim yang disetujui, dan worker antrean database. Uji pengiriman dari lingkungan yang dituju. Kode berlaku 30 menit dan hanya digunakan sekali.

Migrasi M02 menambah tabel organisasi minimum, karyawan, pengguna, sesi, kode reset, scope, Spatie, dan audit. Rollback M02 menghapus tabel identitas beserta isinya. Siapkan cadangan dan rencana pemulihan sebelum rollback pada instalasi yang sudah berisi data.

## Operasi dan batas akses

- NIK tidak dapat diubah atau dihapus melalui model aplikasi. Format yang mengandung `@` ditolak oleh MySQL. Login menganggap masukan berisi `@` sebagai email dan mencocokkannya secara konsisten setelah normalisasi. Gunakan model karyawan saat menambah identitas melalui aplikasi.
- Kegagalan kata sandi dihitung per akun dan IP selama satu menit, termasuk ketika percobaan memakai alias email dan NIK bergantian. Verifikasi 2FA mempunyai pembatas terpisah. Gunakan cache dengan atomic lock yang dibagi oleh seluruh instance bila aplikasi dijalankan pada beberapa host. Cache file bawaan melayani satu host.
- Admin membuat akun untuk karyawan yang sudah terdaftar dan mengajukan reset. Untuk akun tanpa email, operator server mengeluarkan kode hanya jika permintaan aktif tersedia dan identitas sudah diverifikasi. Admin aplikasi tidak menerima kode dari UI. Kode reset tersimpan sebagai hash dan diserahkan lewat email atau secara privat oleh operator.
- Rahasia TOTP terenkripsi dan recovery code tersimpan sebagai hash. Kode pemulihan ditampilkan sekali. Ubah atau nonaktifkan 2FA dengan kata sandi saat ini dan faktor yang masih berlaku. Aktivasi dan perubahan 2FA serta reset kata sandi membatalkan sesi lama. Penonaktifan dan pengaktifan kembali akun juga mengganti versi sesi.
- Peran bawaan adalah Super Admin, Admin Aset, Petugas Aset, Pimpinan, dan Karyawan. Izin administrasi awal diberikan kepada Super Admin. Admin yang diberi izin kelola akun dan scope eksplisit dapat mengelola akun di cakupannya yang belum diberi akses istimewa. Penetapan peran dan scope serta pengelolaan katalog memerlukan Super Admin dan izin yang sesuai. Nama izin aplikasi dan peran bawaan dipertahankan untuk menjaga kontrak dengan kode.
- `OrganizationScope` mengekspansi cabang ke divisi dan departemen, serta divisi ke departemen. Grant departemen tidak mengizinkan akses cabang atau divisi induknya. `ManagedAccounts` dan `UserPolicy` memeriksa izin dan scope pada daftar, URL langsung, dan mutasi. Query aset akan memakai cabang lokasi fisik saat modul aset tersedia. Gate `update-global-settings` memerlukan Super Admin dan izin pengaturan.
- Audit identitas menyimpan aktor, subjek, waktu, alasan, dan snapshot field yang diizinkan. Audit ditulis dalam transaksi perubahan. Kode reset, rahasia TOTP, kode pemulihan, dan kata sandi tidak diteruskan ke audit.

## Verifikasi

Ikuti cara menyiapkan database uji MySQL terisolasi pada panduan fondasi. Jalankan `php artisan migrate --seed`, `php vendor/bin/phpunit --filter IdentityTest`, suite penuh, `php vendor/bin/pint --test`, dan `npm run build`. Uji email otomatis memakai notification fake. Kiriman SMTP perlu diverifikasi saat penyedia yang disetujui tersedia. [Panduan fondasi](foundation.md)

Verifikasi lokal pada PHP 8.4.5 dan MySQL 8.0.30 lulus 18 tes dengan 280 assertion. Migrasi M02 berhasil di-rollback dan diterapkan ulang. Uji worker database membaca payload terenkripsi dan mengirim melalui transport email in-memory. Dalam uji paralel MySQL, tepat satu dari dua proses berhasil memakai kode pemulihan yang sama. Browser memverifikasi login NIK, layar akun dan scope, tema per akun, bahasa Tionghoa Sederhana, serta fokus papan ketik di viewport 390 px. Detektor Impeccable mengembalikan nol temuan.

Layout guest dan workspace memakai token serta komponen dari sistem desain yang ada. Komponen baru menambahkan label, pesan validasi, dan kelompok formulir. Formulir mengubah state melalui rute Laravel dengan CSRF, sementara kontrol tema memakai Alpine. [Keputusan teknis M02](adr/0002-identity-boundaries.md)
