# M05 — Katalog aset dan atribut dinamis

Issue [#9](https://github.com/surega02/asset-management-system/issues/9). Buka **Katalog aset** dari ruang kerja. Katalog ini dipakai bersama oleh seluruh perusahaan.

| PRD | Perilaku | Verifikasi |
| --- | --- | --- |
| MST-001 | Kategori bertingkat menolak induk yang membentuk siklus. Kategori aktif yang memiliki subkategori atau jenis aktif tidak dapat diarsipkan. | `AssetCatalogTest` menguji aksi HTTP. `AssetCatalogIntegrityTest` menjalankan dua proses MySQL yang mencoba menukar induk secara bersamaan. |
| MST-002 | Jenis aset menentukan tipe nilai, opsi, batas, kewajiban isi, dan urutan atribut. Revisi menyimpan versi baru. | `AssetCatalogTest` menguji validasi dan versi. `AssetCatalogIntegrityTest` menguji pembacaan nilai JSON yang masih merujuk versi arsip. |
| AST-001 | Default awal adalah status **Tersedia** dengan kode `AVAILABLE` dan kondisi **Baik** dengan kode `GOOD`. Petugas dapat memilih nilai aktif lain saat registrasi. | `AssetCatalogTest` menguji seed, penggantian default, penolakan arsip default, dan resolver bersama. Registrasi aset dikerjakan pada M06. |

### Izin dan perubahan

- `asset-catalog.view` memberi akses baca. Pengguna selain Super Admin memerlukan grant organisasi untuk mengakses katalog bersama. `asset-catalog.manage` menambah hak tulis. Hanya Super Admin dengan kedua izin yang dapat mengganti default perusahaan. Perubahan tercatat di audit.
- Kode master tetap setelah dibuat. Jenis aset tetap berada di kategori awal agar perubahan katalog tidak memindahkan klasifikasi aset yang sudah tercatat. Kategori dapat dipindahkan dengan memilih induk yang valid. Pengarsipan mempertahankan record dan FK.
- Aksi master mengunci baris tunggal katalog sebelum mengubah pohon atau default. Tetapkan pengganti aktif sebagai default sebelum mengarsipkan default lama. Status, kondisi, dan status aktif aset memiliki makna berbeda.
- Aksi atribut mengunci jenis aset. Setiap versi menyimpan label, tipe, opsi, batas dan konfigurasi miliknya. Indeks unik membatasi satu versi aktif per kunci dalam satu jenis aset. Mengarsipkan versi menghapusnya dari form baru, sedangkan histori membaca definisi asli dengan FK ID versi.

### Kontrak M06 dan M09

- Setelah otorisasi, panggil `ResolveAssetCatalog::fields($typeId)` untuk metadata field pada form. Form dan pratinjau impor memanggil `resolve($categoryId, $typeId, $values, $statusId, $conditionId)`. ID status/kondisi yang kosong mengambil default aktif. Eksekusi registrasi dan impor memanggil ulang resolver dengan `forWrite: true` di dalam transaksi.
- Kirim nilai sebagai peta kunci atribut. Resolver menolak kunci asing atau yang sudah diarsipkan dan memberi hasil dengan kunci berupa ID definisi versi. Gunakan ID tersebut untuk FK `asset_attribute_values.attribute_definition_id` pada M06. Baca nilai lama tanpa filter `is_active`.
- Tipe yang tersedia adalah teks, bilangan bulat, desimal, tanggal, boolean, dan daftar pilihan. Bilangan menerima sampai 12 digit bagian bulat, dan desimal sampai 6 digit pecahan. Desimal dikembalikan sebagai string presisi. Tanggal memakai YYYY-MM-DD. Boolean mendukung nilai asli dan teks `true`, `false`, `1`, `0`.

### Rilis

Jalankan `php artisan migrate --seed --force` untuk membuat katalog, default awal, dan izin. Migrasi mengisi default awal sekali. Seeder identitas dapat dijalankan kembali tanpa mengganti default. Untuk rollback aplikasi, gunakan versi kode sebelumnya dan simpan tabel katalog agar histori tetap tersedia.

Jalankan `php vendor/bin/phpunit --filter 'AssetCatalogTest|AssetCatalogIntegrityTest'` dengan `AMS_TEST_MYSQL=1` pada skema uji MySQL berakhiran `_test`. Jalankan suite penuh dengan `php vendor/bin/phpunit`, Pint dengan `php vendor/bin/pint --test`, dan build dengan `npm run build`.
