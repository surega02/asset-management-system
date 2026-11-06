# ADR 0002 — Batas identitas dan akses M02

M02 membuat tabel referensi organisasi yang dibutuhkan FK identitas. M03 dan M04 melanjutkan pengelolaan master dan karyawan dari tabel yang sama. Kolom referensi audit khusus aset dan impor ditambahkan saat tabel rujukannya tersedia.

Spatie menyimpan peran dan izin. Registrasi izin otomatis ke Gate dinonaktifkan agar nama izin dinamis tidak dapat mendahului hasil Policy. Action mengunci baris peran Super Admin sebelum mutasi administratif, memuat ulang aktor dan akun, lalu memeriksa izin serta scope. Baris akun juga menjadi batas serialisasi penebusan reset dan pemakaian faktor kedua.

Akun dengan email menerima kode reset sekali pakai melalui notifikasi antrean terenkripsi setelah commit. Worker memeriksa akun, tujuan email, dan hash kode terbaru sebelum pengiriman. Kode disalin dari pesan dan tidak ditempatkan dalam URL. Akun tanpa email memakai permintaan oleh admin aplikasi dan penyerahan privat oleh operator server yang telah memverifikasi identitas. Operator berwenang adalah batas kepercayaan untuk proses tanpa email.

TOTP memakai Google2FA dan SVG QR dibuat lokal oleh BaconQrCode. Satu langkah waktu TOTP hanya dapat dipakai sekali per akun. Kode pemulihan acak berentropi tinggi di-hash dan dikonsumsi dalam transaksi. Konfigurasi 2FA yang belum dikonfirmasi dan tampilan sekali kode pemulihan berada dalam sesi terenkripsi dengan respons no-store.
