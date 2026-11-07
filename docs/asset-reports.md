# M10 — Dashboard dan laporan aset

Ruang kerja menampilkan ringkasan berdasarkan cabang lokasi fisik yang diizinkan, hingga enam lokasi dengan jumlah unit terbanyak, dan lima perubahan terbaru dalam 30 hari. Bagian **Aset saya** menampilkan penugasan langsung yang aktif, termasuk penugasan pribadi di cabang lain. Bagian pribadi mengikuti aturan baca aset dan terpisah dari rekap dan ekspor operasional.

**Daftar terkini** dan **Rekap** menyediakan pencarian, filter cabang fisik dan aktif/nonaktif, serta rentang tanggal berdasarkan pembelian, registrasi, atau perubahan terakhir. Rekap dapat dikelompokkan menurut cabang fisik, departemen penanggung jawab, karyawan, kategori, atau kondisi. Untuk penugasan ke karyawan, rekap departemen memakai departemen karyawan saat ini. Untuk penugasan langsung ke departemen, rekap memakai departemen tersebut. Unit tanpa departemen atau karyawan yang terkait masuk ke kelompok kosong pada dimensi terkait.

**Riwayat perubahan** memakai waktu audit dan menampilkan pelaksana, alasan, serta nilai sebelum dan sesudah untuk lokasi, penanggung jawab, status, dan kondisi. Penutupan dan pembukaan penugasan karena perubahan lifecycle muncul sebagai kejadian penugasan. Status dan kondisi yang berubah bersamaan ditampilkan terpisah. Cakupan laporan mengikuti lokasi fisik aset saat laporan diminta. Nama referensi ditampilkan dari master berdasarkan ID yang disimpan di audit. Periode menyaring kejadian dan tidak merekonstruksi posisi aset pada tanggal lampau.

Unduhan CSV dan XLSX memakai kolom, urutan, dan filter yang sama dengan laporan, mencakup semua halaman, dan mengecek izin untuk setiap permintaan. Ekspor berjalan sinkron. CSV menetralkan awalan formula dan XLSX menulis teks sebagai sel teks. Rentang hari inklusif mengikuti zona waktu aplikasi.

- **RPT-001 / IAM-003:** dasbor dan agregasi memakai cakupan fisik, sementara Aset saya hanya memakai penugasan langsung.
- **RPT-002 / T-012:** laporan terkini, pagination, lima dimensi rekap dan kedua format ekspor. Tes mengecek ulang akses setelah aset pindah cabang.
- **RPT-003 / AUD-001 / T-013:** kejadian bulan Maret tetap terlihat dalam periode Maret, sementara daftar terkini mencerminkan lokasi bulan Juni.

Rilis memerlukan build frontend dan ekstensi PHP ZIP serta XMLWriter untuk XLSX. M10 tidak menambah migrasi, izin baru, atau worker. Perintah verifikasi terfokus adalah `php vendor/bin/phpunit --filter AssetReportTest` dengan MySQL dan `AMS_TEST_MYSQL=1`.

Uji browser menggunakan data sintetis dua cabang. [Dashboard petugas Jakarta](evidence/m10/workspace-desktop-id.jpg) menghitung tiga unit operasional. Unit Bandung yang ditugaskan langsung muncul pada bagian pribadi. Bukti lainnya mencakup [tabel ponsel dengan fokus keyboard](evidence/m10/report-mobile-dark.jpg) dan [filter perubahan lokasi dengan nilai sebelum dan sesudah](evidence/m10/event-desktop-dark.jpg). Hasil kosong, rekap departemen, serta tampilan Indonesia, Inggris, dan Tionghoa Sederhana diperiksa di browser.
