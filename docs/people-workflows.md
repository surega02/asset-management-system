# M04 — Karyawan dan penugasan pejabat

Issue [#7](https://github.com/surega02/asset-management-system/issues/7). Menu **Karyawan** dan **Penugasan pejabat** tersedia dari ruang kerja sesuai izin dan cakupan data.

| PRD | Perilaku | Tes |
| --- | --- | --- |
| EMP-001 / IAM-001 | Buat dan ubah profil, hitung masa kerja dari tanggal bergabung, pertahankan NIK termasuk saat nonaktif. Pembuatan karyawan tidak membuat akun. | `PeopleWorkflowsTest`: pendaftaran, NIK tetap, tidak ada akun otomatis, akses lintas cakupan, CSV/XLSX. |
| EMP-002 | Periksa cakupan sumber dan tujuan, peringatkan penugasan langsung, simpan unit lama/baru, aktor, waktu, dan alasan. Assignment dan lokasi aset tetap. | `PeopleWorkflowsTest` dan `PeopleConcurrencyTest`: cakupan, histori/audit, perpindahan pemegang aset. |
| EMP-003 | Karyawan yang punya assignment aktif langsung tidak dapat dinonaktifkan. | `PeopleConcurrencyTest`: dua urutan kompetisi antara assignment dan deaktivasi pada MySQL. |
| ORG-002 / T-008 | HOD untuk departemen; Director untuk divisi, cabang, atau perusahaan. Tanggal inklusif, Acting mendahului definitif; overlap sejenis ditolak. | `PeopleWorkflowsTest` dan `PeopleConcurrencyTest`: prioritas dan kembali ke definitif, batas inklusif, scope, dua insert paralel. |

### Cakupan dan integritas

- `employees.view` + grant organisasi memberi akses baca karyawan sesuai departemen; `employees.manage` mengizinkan mutasi dalam cakupan itu. Transfer memeriksa departemen asal dan tujuan. Untuk penempatan baru, pilih unit dan lokasi kerja aktif di cabang departemen. Jabatan adalah referensi katalog. Status karyawan dan status akun login terpisah.
- `appointments.view` dan `appointments.manage` memakai grant unit yang sesuai, serta cakupan kandidat saat membuat penugasan. Penugasan perusahaan memerlukan izin Super Admin yang relevan. Simpan jadwal dengan tanggal akhir opsional, atau ubah tanggal akhir dengan alasan. Histori tersimpan dalam audit. Transaksi menyerialkan penulisan jadwal pada kunci stabil `(role_kind, scope_type, scope_id)` dengan sentinel `0` untuk perusahaan.
- Registrasi dan reaktivasi M06 memanggil `DirectEmployeeAssignments::lockEligibleAssignee()` dalam transaksi sebelum membuat assignment langsung. Pergantian penanggung jawab M07 memakai lock yang sama. Deaktivasi karyawan mengambil lock tersebut dan membaca assignment aktif dengan locking read. Tes konkurensi menggunakan tabel dan aksi registrasi M06.
- Daftar peringatan hanya menampilkan kode/nama aset jika izin `assets.view` dan cakupan lokasi fisik atau hak baca personal terpenuhi. Jumlah assignment diperlukan untuk tindakan perpindahan/deaktivasi. Pengiriman notifikasi dalam aplikasi mengikuti M11.
- Impor karyawan menerima CSV UTF-8 atau lembar pertama XLSX. Unduh template dari layar impor; ID unit diambil dari opsi bercakupan, NIK XLSX harus berupa teks, tanggal CSV memakai YYYY-MM-DD. Maksimal 1.000 baris data dan 10 MB. Parser tidak mengevaluasi formula, mengikuti tautan eksternal, atau mengekstrak ZIP ke disk; ada batas ukuran setelah dekompresi.
- Unggah mempersiapkan batch tanpa membuat karyawan. Proses hingga 50 baris sekali klik; kembali ke batch untuk melanjutkan. Identitas batch per pengunggah dan hash berkas, nomor baris unik, dan sukses terminal. Setiap baris berhasil menyimpan karyawan, audit, dan hasil dalam satu transaksi; kegagalan tidak membatalkan baris lain. Coba ulang baris gagal secara eksplisit; untuk isi yang keliru, unggah hanya baris yang telah diperbaiki. Payload sementara terenkripsi; payload sukses dibuang. Scheduler membersihkan batch dan hasil setelah 90 hari. Audit dipertahankan.

### Rilis dan pengujian

- Jalankan `php artisan migrate --seed --force` untuk histori, jadwal, lock, batch, dan izin baru. Pada server yang sudah termigrasi, seeder izin bisa diulang: `php artisan db:seed --class=IdentitySeeder --force`. Pastikan PHP memiliki ZIP dan SimpleXML serta scheduler berjalan. Untuk rollback aplikasi, kembali ke kode sebelumnya dan pertahankan tabel agar histori/hasil tetap tersedia.
- Tes terfokus: `php vendor/bin/phpunit --filter 'PeopleWorkflowsTest|PeopleConcurrencyTest'`. Atur `AMS_TEST_MYSQL=1` dan gunakan skema MySQL khusus berakhiran `_test` dengan migrasi aktif. Tes konkurensi membaca `information_schema.PROCESSLIST` untuk memastikan proses benar-benar menunggu lock sebelum transaksi pertama dilepas.
### Bukti UAT

UAT memakai data sintetis. Di desktop, perpindahan Bandung ke Jakarta menampilkan unit lama, unit baru, aktor, dan alasan. Fokus keyboard pada filter status terlihat. Di layar 390 px dalam tema gelap, daftar menunjukkan Acting saat periode tumpang tindih dan mengembalikan prioritas ke definitif setelah Acting berakhir. Impor CSV dalam locale Mandarin menampilkan satu baris berhasil dan satu baris gagal karena tanggal tidak valid.

- [Perpindahan karyawan dan riwayat di desktop](evidence/m04/employee-transfer-desktop.png)
- [Pejabat efektif di ponsel dalam bahasa Inggris](evidence/m04/appointments-mobile.png)
- [Hasil parsial impor CSV di ponsel dalam bahasa Mandarin](evidence/m04/import-partial-mobile-zh.png)

GitHub Actions memverifikasi migrasi, build Tailwind, Pint, dan PHPUnit dengan PHP 8.3 dan MySQL 8.4. Suite lulus dengan 30 tes dan 533 asersi, termasuk XLSX dan tes konkurensi. [Log CI implementasi](https://github.com/surega02/asset-management-system/actions/runs/42192790286)
