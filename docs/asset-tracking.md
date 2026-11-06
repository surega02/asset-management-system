# M07 — Penugasan dan perpindahan fisik

Issue [#13](https://github.com/surega02/asset-management-system/issues/13). Buka detail aset dan pilih **Penugasan dan lokasi**. Layar menampilkan penanggung jawab serta lokasi fisik saat ini, formulir sesuai izin, dan dua riwayat yang dapat dipaginasi secara independen.

| PRD | Perilaku | Verifikasi |
| --- | --- | --- |
| ASN-001 / T-003 | Pergantian menutup penugasan lama dan membuat tepat satu penugasan aktif. Permintaan kedua dengan ID penugasan lama ditolak. | `AssetTrackingTest` dan `PeopleConcurrencyTest` menjalankan perubahan berulang serta dua proses yang menunggu lock aset yang sama. |
| ASN-002 | Pergantian penanggung jawab mempertahankan lokasi fisik. Perpindahan fisik mempertahankan penugasan dan mencatat lokasi asal/tujuan. | `AssetTrackingTest` memeriksa ID penugasan, lokasi, histori, dan audit sebelum/sesudah. |
| EMP-002 / EMP-003 | Aksi memeriksa ulang unit serta status karyawan setelah memperoleh lock. Deaktivasi karyawan ditolak saat penugasan langsung masih aktif. | `PeopleConcurrencyTest` menguji transfer, deaktivasi, dan pergantian penanggung jawab secara bersamaan. |
| IAM-003 / T-006 / T-007 | Izin operasional mengikuti cabang lokasi fisik. Perpindahan memerlukan cakupan asal dan tujuan. Penugasan langsung memberi baca personal. | `AssetTrackingTest` memeriksa URL, daftar, dan query operasional sebelum serta sesudah perpindahan lintas cabang. |
| AUD-001 | Aktor, alasan, waktu, dan data sebelum/sesudah tersimpan bersama perubahan. | Injeksi kegagalan audit memverifikasi rollback penugasan dan histori lokasi. |

### Izin dan transaksi

- `assets.reassign` dan `assets.move` menambah dua aksi terpisah. Keduanya memerlukan `assets.view` dan cakupan cabang fisik. Tujuan penugasan harus aktif serta tercakup sesuai jenis penerima. Tujuan lokasi fisik harus aktif dan berada dalam cabang yang dicakup oleh izin perpindahan.
- Aksi mengunci akun, aset, kemudian histori atau penugasan yang relevan. Pergantian ke karyawan memakai lock M04 sebelum memvalidasi unit dan status terbaru. Penugasan lama ditutup pada waktu yang sama dengan pembukaan penugasan baru. Constraint M06 tetap membatasi satu penugasan aktif.
- Form mengirim ID penugasan aktif atau ID histori lokasi terbaru. Aksi mencocokkan ID tersebut setelah mengunci aset. Kiriman ulang atau formulir yang tertinggal ditolak agar perubahan lain dapat ditinjau sebelum mengirim ulang.
- Riwayat menggunakan Policy baca aset dan menampilkan nama aktor, target/lokasi, waktu, dan alasan. Hak baca personal mengikuti penugasan aktif yang langsung menunjuk karyawan. Perubahan ke departemen atau lokasi tidak memberi hak personal.
- Gunakan `Assets::operational($actor)` untuk cakupan fisik laporan dan ekspor operasional. Panggil Policy ekspor pada M10 sebelum menjalankan query. M07 menguji batas query ini dan tidak menambah endpoint ekspor. Notifikasi penugasan tetap bagian M11.

### Rilis dan pengujian

M07 memakai tabel M06. Jalankan `php artisan db:seed --class=IdentitySeeder --force` untuk menambahkan izin. Beri izin yang sesuai kepada peran operasional. Rollback kode mempertahankan riwayat di tabel M06.

Jalankan `php vendor/bin/phpunit --filter 'AssetTrackingTest|PeopleConcurrencyTest'` dengan `AMS_TEST_MYSQL=1` pada skema uji berakhiran `_test`. Tes paralel memeriksa `information_schema.PROCESSLIST` untuk memastikan proses menunggu lock sebelum transaksi pertama dilepas.

### Bukti pengujian

Tiga tes fitur lulus dengan 75 asersi dan dua tes paralel lulus dengan 22 asersi di MySQL 8.0.30 lokal. Suite penuh lokal terhenti saat autoloader salinan runtime tidak tersedia. GitHub Actions menjalankan suite penuh pada MySQL 8.4 dan lulus dengan 48 tes serta 835 asersi. Migrasi, Pint, dan build Tailwind lulus. [CI implementasi](https://github.com/surega02/asset-management-system/actions/runs/42237780902)

UAT memakai data sintetis. Penggantian penanggung jawab mempertahankan lokasi fisik Jakarta. Perpindahan Jakarta ke Bandung mempertahankan penugasan dan menampilkan histori asal, tujuan, aktor, serta alasan. Pemeriksaan mencakup fokus keyboard, desktop terang, ponsel gelap 390 px, dan locale Indonesia, Inggris, serta Mandarin. Detektor Impeccable melaporkan nol temuan pada Blade aset.

- [Pergantian penanggung jawab di desktop](evidence/m07/assignment-desktop.png)
- [Perpindahan fisik lintas cabang di ponsel](evidence/m07/move-mobile.png)
- [Histori pelacakan dalam bahasa Mandarin](evidence/m07/tracking-mobile-zh.png)
