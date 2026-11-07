# M09 — Impor aset CSV/XLSX

Di daftar aset, buka **Impor aset**. Unduh template CSV atau XLSX dan referensi nilai master sesuai cakupan Anda. Isi maksimal 1.000 baris dan 10 MB per berkas. CSV memakai UTF-8 dan pemisah koma. Impor XLSX membaca lembar pertama dan memerlukan nilai biasa, tanpa formula. Gunakan teks ISO `YYYY-MM-DD` untuk atribut tanggal dinamis, dan simpan kode, nomor seri, serta nomor invoice sebagai teks. Atribut dinamis memakai kolom `attr.<key>`. Berkas dapat memuat hingga 64 kolom.

Pada pratinjau, kode kosong menjadi kandidat buat. Kode yang cocok menjadi kandidat perbarui jika aset aktif dan dalam cakupan operasi. Kemiripan nama atau seri memunculkan peringatan. Simpan pilihan sebelum berpindah halaman. Saat dikonfirmasi, baris yang belum diubah memakai tindakan awal yang disarankan. Worker mengalokasikan kode aset baru dalam transaksi baris.

Untuk pembaruan, isi `asset_code` dan `reason`. Isian kosong mempertahankan nilai yang ada. Untuk mengganti jenis lintas kategori, isi kedua ID. Gunakan `employee:<id>`, `department:<id>`, atau `location:<id>` pada `assignee`. Perubahan lokasi dan tanggung jawab masing-masing membuat riwayat lewat aksi domain. Pembaruan aset nonaktif, perubahan lifecycle, dan lampiran tidak dilakukan lewat impor ini.

Setelah impor selesai, perbaiki dan coba ulang baris gagal dari hasil batch. Koreksi menggunakan kode aset dari baris semula. CSV error memuat diagnostik dan nilai unggahan. Jika akan mengunggahnya sebagai batch baru, salin hanya kolom data ke template. Pratinjau, hasil, dan riwayat percobaan dapat dibaca oleh pemilik batch yang masih memiliki izin impor. Data sementara kedaluwarsa setelah 90 hari. Audit bisnis tetap tersimpan.

| Persyaratan | Perilaku dan verifikasi |
|---|---|
| IMP-001 | CSV/XLSX terbatas, pratinjau tanpa mutasi, peringatan kemiripan, pilihan dan koreksi per baris. |
| IMP-002 / T-010 | Setiap baris memiliki transaksi sendiri. Tes memverifikasi hasil 92 sukses, 5 gagal, dan 3 dilewati. |
| IMP-003 / T-009 | Lease dan nomor percobaan mencegah job lama mengulang baris. Tes mencakup rollback sebelum commit, job ganda setelah commit, kedaluwarsa lease, dan percobaan eksplisit. |
| AUD-001 / IAM-003 | Izin dan cakupan terkini diperiksa saat worker berjalan. Audit bisnis dan status sukses masuk dalam transaksi yang sama. |
| OPS-001 | Database queue memproses batch. Scheduler membangunkan batch yang terputus dan membersihkan hasil sementara. |

Jalankan `php artisan migrate --seed --force` dan build frontend. Migrasi menambahkan tiga tabel impor. Berikan `assets.import` dan `assets.view` kepada pengguna berwenang, ditambah `assets.create` untuk buat atau `assets.update` untuk pembaruan. Perubahan status/kondisi, lokasi, atau penanggung jawab juga memerlukan izin aksi yang sesuai. Pastikan `ext-zip` dan `ext-simplexml` aktif, dan atur batas unggahan HTTP, `upload_max_filesize`, serta `post_max_size` agar permintaan multipart berkas 10 MB diterima.

Jalankan worker tersupervisi dengan `php artisan queue:work database --timeout=60 --tries=1`, dengan `DB_QUEUE_RETRY_AFTER` lebih besar dari 60 detik. Scheduler perlu menjalankan `php artisan schedule:run` setiap menit. Worker memproses paling banyak 25 baris per job dan menjalankan paling banyak satu lease aktif per batch. Lease kedaluwarsa setelah 120 detik. Setelah dua pemulihan otomatis untuk baris yang sama, gangguan berikutnya dicatat sebagai gagal sehingga pengguna dapat mengulangnya secara eksplisit. Untuk rollback kode, hentikan worker impor dan pertahankan tabel supaya hasil batch tetap tersimpan.

Uji browser menggunakan empat baris sintetis. [Pratinjau desktop](evidence/m09/import-preview-id.jpg) menampilkan peringatan kemiripan dan biaya tidak valid. Worker menghasilkan [dua sukses, satu gagal, dan satu dilewati](evidence/m09/import-result-mobile-light.jpg). Koreksi biaya memicu percobaan baru dan membuat satu unit tambahan, dengan [kedua percobaan tetap terlihat](evidence/m09/import-retry-mobile-light.jpg). Hasil juga diperiksa dalam bahasa Inggris dan Tionghoa Sederhana.
