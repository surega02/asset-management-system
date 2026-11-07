# M11 — Notifikasi, audit, dan pengaturan sistem

Notifikasi AMS tersimpan di dalam aplikasi. Perubahan domain mencatat kejadian dalam transaksi yang sama. Scheduler menerbitkan kejadian yang sudah commit setiap menit. Kejadian yang dibatalkan ikut hilang saat rollback. Penerbit memeriksa akun aktif, izin dan cakupan terkini. Kotak masuk dan aksi tandai dibaca memeriksa ulang akses. Isi notifikasi berupa jenis kejadian dan tautan ke sumber yang memiliki otorisasi sendiri.

| Kejadian | Penerima |
|---|---|
| Impor aset atau karyawan selesai | Pemilik batch yang masih memiliki izin impor. |
| Transfer karyawan yang masih memegang aset | Admin aset yang boleh membaca karyawan di penempatan terkini dan Super Admin yang berizin. |
| Deaktivasi atau reaktivasi aset | Admin aset yang berizin baca di cabang fisik aset dan Super Admin yang berizin. |
| Perubahan penanggung jawab | Akun penanggung jawab karyawan lama dan baru yang masih boleh membaca aset. |
| Masa Plt. mendekati akhir | Super Admin dengan izin baca penugasan. Pemeriksaan tiap jam mencari penugasan efektif yang berakhir dalam tujuh hari. |

Penerbitan aman diulang dengan kunci kejadian dan penerima unik. Scheduler memproses hingga 100 kejadian per putaran. Indikator antrean menampilkan pekerjaan siap yang terlambat setidaknya lima menit dan kegagalan dalam 24 jam. Jika hasil pemeriksaan sudah kedaluwarsa, halaman menampilkan bahwa hasil terbaru belum tersedia.

Log audit memerlukan `audit.view`. Super Admin yang memiliki izin tersebut dapat melihat kejadian sistem. Pengguna lain dengan izin audit dan baca aset hanya melihat kejadian aset dalam cakupan fisik terkini. Filter tersedia untuk pelaksana, jenis kejadian, kode aset, dan tanggal. Tampilan bersifat baca saja dan audit bisnis tidak dihapus oleh scheduler. Snapshot memakai daftar field yang diizinkan. Penampil juga menyamarkan pola kredensial, token, atau alamat surel yang dikenali dalam catatan lama. Catatan lama tetap tersimpan sebagai jejak audit.

Super Admin dengan `settings.manage` dapat mengubah bahasa bawaan pengunjung dan akun baru, serta ukuran dan informasi opsional template label. Preferensi akun yang sudah ada tetap berlaku. QR dan kode aset selalu dicetak. Preview label yang sudah dibuat mengikuti batas field yang berlaku saat dibuka. Perubahan pengaturan dicatat di audit.

Rilis memerlukan `php artisan migrate --seed --force` dan build frontend. Migrasi menambah tabel pengaturan, antrean kejadian notifikasi, kotak masuk, dan indeks filter audit. Jalankan scheduler setiap menit. Pemeriksaan kesehatan menggunakan cache yang dapat dibaca proses web dan scheduler. Jika rollback kode diperlukan, pertahankan tabel dan data M11.

Verifikasi `NTF-001`, `AUD-001`, dan `OPS-001` menggunakan `php vendor/bin/phpunit --filter SystemAdministrationTest` pada MySQL.

Uji browser memakai data sintetis dan scheduler lokal. Bukti mencakup [notifikasi yang ditandai dibaca](evidence/m11/notification-mobile.jpg), [filter audit dan nilai sebelum/sesudah](evidence/m11/audit-desktop.jpg), serta [template dan kesehatan antrean](evidence/m11/settings-desktop.jpg). Formulir cetak juga diperiksa setelah field opsional dikeluarkan dari template Standar.
