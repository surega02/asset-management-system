# M03 — Master organisasi dan lokasi

Issue [#5](https://github.com/surega02/asset-management-system/issues/5). Menu **Master organisasi** tersedia dari ruang kerja untuk akun yang memiliki izin dan cakupan data yang sesuai.

| PRD | Perilaku | Bukti |
| --- | --- | --- |
| ORG-001 | Cabang → divisi → departemen; jabatan merupakan katalog bersama. Unit pemilik tidak berubah setelah pembuatan. Arsip dan aktivasi kembali mempertahankan referensi. | `OrganizationMastersTest`: buat dan ubah kelima master, arsip, aktivasi kembali, audit, dan FK RESTRICT di MySQL. |
| MST-001 | Lokasi fisik bisa bertingkat dalam satu cabang. Pemilihan diri sendiri, keturunan, dan induk lintas cabang ditolak. | `OrganizationMastersTest`: siklus langsung dan bertingkat, FK komposit, induk diarsipkan, serta audit perubahan. `LocationConcurrencyTest`: dua proses MySQL menunggu lock cabang; tepat satu relasi tersimpan dan yang lain ditolak. |
| IAM-003 | Daftar, detail, opsi induk dan perubahan menggunakan izin serta cakupan. | `OrganizationMastersTest`: akun baca saja, akun tanpa izin atau cakupan, ID lintas cabang, dan grant departemen tanpa akses ke divisi/cabang. |

### Data dan operasi

- Gunakan `organization.view` untuk baca dan tambahkan `organization.manage` untuk perubahan. Super Admin dengan izin yang sesuai bisa membuat cabang dan mengelola katalog jabatan. Pengguna dengan izin baca dan grant organisasi bisa membaca katalog jabatan. Grant cabang mencakup divisi, departemen, dan lokasi fisik di cabang itu; grant divisi/departemen tidak memperluas akses ke lokasi fisik.
- Arsip mengubah `is_active` dan tidak mengubah anak, referensi karyawan, atau cakupan akses. Pemilihan unit pemilik baru mensyaratkan unit aktif. Rantai lokasi yang dipilih sebagai induk baru harus aktif. Pemindahan lokasi dalam cabang tersedia; perubahan cabang atau divisi pemilik tidak tersedia melalui form master.
- Tidak ada migrasi tambahan. Migrasi M02 sudah menyediakan FK RESTRICT, indeks pemilik dan kode yang unik dalam unit, serta FK lokasi `(parent_id, branch_id)` ke indeks unik `(id, branch_id)`. Penguncian cabang memakai PK. Aksi tulis memakai lock bersama pada peran Super Admin untuk koordinasi dengan perubahan katalog IAM, lalu lock akun, cabang, dan baris yang dipakai. Pembacaan induk dengan lock melihat perubahan terbaru yang sudah commit. Mutasi dan audit disimpan dalam satu transaksi.
- Setelah rilis, jalankan `php artisan db:seed --class=IdentitySeeder --force` untuk menambahkan izin baru ke katalog dan ke peran Super Admin secara idempoten. Grant bagi peran lain diberikan melalui menu **Peran dan izin**. Jika rilis dibatalkan, kembali ke versi aplikasi sebelumnya tanpa rollback skema. Data master tetap tersimpan.

### Verifikasi

Pada MySQL 8.0.30 lokal dan PHP 8.4.5, dengan `AMS_TEST_MYSQL=1` serta skema uji berakhiran `_test`:

- `php artisan migrate --seed --force`: migrasi M02 dan seed berhasil pada skema baru.
- `php vendor/bin/phpunit --filter 'OrganizationMastersTest|LocationConcurrencyTest'`: 5 tes, 134 asersi.
- `php vendor/bin/phpunit`: 23 tes, 414 asersi.
- `php vendor/bin/pint --test`, `npm run build`, dan detektor Impeccable pada Blade master: lulus.

UAT dengan data sintetis: daftar, pencarian kosong, filter arsip, aktivasi kembali, pembuatan lokasi, pilihan induk sesuai cabang, dan penolakan siklus dengan input tetap terisi. Periksa fokus keyboard yang terlihat, tampilan desktop terang dan ponsel gelap (390 px), serta locale Indonesia, Inggris dan Mandarin.

- [Daftar lokasi — desktop terang](evidence/m03/locations-desktop.png)
- [Validasi siklus — ponsel gelap](evidence/m03/location-validation-mobile.png)
- [Daftar lokasi berbahasa Mandarin — ponsel gelap](evidence/m03/locations-mobile-zh.png)
