# M06 — Registrasi, lampiran, dan siklus hidup aset

Issue [#11](https://github.com/surega02/asset-management-system/issues/11). Buka **Aset → Registrasi aset**, pilih jenis, lalu isi detail unit dan satu penanggung jawab.

| PRD | Perilaku | Tes |
| --- | --- | --- |
| AST-001 / T-001, T-002, T-015 | Registrasi mengalokasikan kode saat transaksi tulis dan menyimpan aset, assignment, atribut versi, lokasi awal, serta audit. Default dari katalog bisa diganti. | `AssetLifecycleTest` dan `PeopleConcurrencyTest::test_t001_parallel_registration_uses_unique_codes_and_one_assignment_per_asset`. |
| AST-002 | Foto dan invoice opsional. Konten dan ukuran diperiksa sebelum penyimpanan privat. Unduhan memerlukan Policy aset. | `AssetLifecycleTest::test_private_attachments_validate_content_and_asset_scope_for_download`. |
| AST-003 / AST-004 / T-004, T-005 | Status operasional dan kondisi terpisah dari `is_active`. Penonaktifan menutup penugasan dengan alasan. Pengaktifan kembali membuat penugasan baru. | `AssetLifecycleTest` menguji rollback audit dan reaktivasi tanpa penanggung jawab. |
| ASN-001 / AUD-001 | CHECK tepat satu target, indeks unik penugasan aktif, transaksi domain dan audit tersanitasi. | Tes MySQL menguji constraint, alokasi paralel, dan balapan antara registrasi aset dan deaktivasi karyawan. |

### Izin, data, dan transaksi

- `assets.view` memungkinkan pembacaan sesuai cabang lokasi fisik atau penugasan langsung kepada karyawan pemilik akun. `assets.create`, `assets.lifecycle`, dan `assets.update-state` memerlukan cakupan operasional pada cabang lokasi fisik. Penanggung jawab baru juga harus berada dalam cakupan yang diizinkan. Cakupan divisi atau departemen tidak memberi akses fisik ke seluruh cabang.
- `RegisterAsset` memakai resolver M05 dan mengunci karyawan penerima bersama M04. Nomor dialokasikan dari satu baris sequence di dalam transaksi. Aset, penugasan aktif, histori lokasi, metadata lampiran, nilai atribut, dan audit berhasil atau batal bersama. Gunakan aksi yang sama pada eksekusi impor M09.
- Untuk M07, lock aset sebelum menutup atau membuka penugasan. Gunakan `AssetTargets` untuk memilih penerima dan mempertahankan lock karyawan. Kolom generated membatasi satu penugasan aktif. Aksi domain menjamin jumlahnya tepat satu untuk aset aktif dan nol untuk aset nonaktif.
- QR belum dipublikasikan pada M06. Token acak tetap stabil dan tidak masuk tampilan detail atau audit. Aksi ubah status/kondisi, deaktivasi, dan reaktivasi menyimpan alasan dan perubahan terkait. Notifikasi dalam aplikasi mengikuti M11.
- Foto JPG/PNG/WebP maksimal 5 MB. Invoice dan dokumen deaktivasi PDF/JPG/PNG maksimal 10 MB. Server memeriksa MIME, struktur PDF atau dekode gambar, dan memakai nama acak. File disimpan di disk `asset-private` dan diunduh sebagai lampiran lewat Policy. File yang sudah ditulis dibersihkan jika operasi database gagal. Kehilangan proses atau disk saat penulisan dapat menyisakan file privat tanpa metadata. Sertakan disk privat dalam prosedur cadangan dan rekonsiliasi penyimpanan.

### Rilis

Jalankan `php artisan migrate --seed --force` untuk tabel aset, penugasan, atribut, histori lokasi, lampiran, deaktivasi, dan FK audit. Aktifkan ekstensi PHP GD dan fileinfo. Atur batas request server agar memuat foto 5 MB dan invoice 10 MB dalam satu request, termasuk overhead multipart. Rollback aplikasi dapat mengembalikan kode dan mempertahankan tabel serta file privat. Rollback migrasi M06 menjatuhkan tabel baru, sehingga hanya sesuai untuk skema uji kosong.

Jalankan `php vendor/bin/phpunit --filter 'AssetLifecycleTest|PeopleConcurrencyTest'` dengan `AMS_TEST_MYSQL=1` dan skema MySQL berakhiran `_test`. Gunakan `php vendor/bin/phpunit`, `php vendor/bin/pint --test`, dan `npm run build` untuk verifikasi penuh.

### Bukti pengujian

Enam tes fitur M06 lulus dengan 94 asersi pada MySQL 8.0.30 lokal. Migrasi M06 juga berhasil di-rollback dan dipasang ulang pada skema uji kosong. Percobaan tes proses paralel lokal kehilangan akses ke autoloader salinan runtime. GitHub Actions menjalankan suite penuh pada MySQL 8.4 dan lulus dengan 43 tes dan 738 asersi, termasuk alokasi nomor paralel, balapan dengan deaktivasi karyawan, rollback transaksi, dan Policy unduhan. Pint serta build Tailwind lulus. [CI implementasi](https://github.com/surega02/asset-management-system/actions/runs/42235204514)

UAT memakai data sintetis. Registrasi memilih Tersedia dan Baik, menyimpan atribut jenis, lalu menampilkan kode unit dan penanggung jawab. Deaktivasi menutup penugasan. Reaktivasi memilih penanggung jawab baru dan mempertahankan kode yang sama. Pemeriksaan mencakup fokus keyboard, desktop terang, ponsel gelap 390 px, serta locale Indonesia, Inggris, dan Mandarin. Detektor Impeccable melaporkan nol temuan pada Blade aset.

- [Registrasi aset dan pilihan default di desktop](evidence/m06/registration-desktop.png)
- [Aset setelah deaktivasi di ponsel](evidence/m06/deactivated-mobile.png)
- [Aset setelah reaktivasi dalam locale Mandarin](evidence/m06/reactivated-mobile-zh.png)
