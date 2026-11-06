# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13, PHP 8.3+, MySQL InnoDB, Blade, Livewire, Alpine.js, dan Tailwind CSS. Arsitektur modular monolith, dengan Laravel Policies dan Spatie Laravel Permission untuk otorisasi serta database queue untuk pekerjaan latar belakang. Target operasional NGINX dan PHP-FPM, baik on-premises maupun cloud.

## Users

- **Petugas Aset** adalah pengguna utama ketika antarmuka perlu memprioritaskan satu alur. Mereka mendaftarkan unit, mencetak label, dan memindai QR di lapangan. Mereka juga memperbarui aset, mengimpor data, dan mencatat perpindahan fisik sesuai izin dan cakupan data.
- **Admin Aset** mengelola data master, aset, dan laporan sesuai izin dan cakupan data. **Super Admin** mengelola pengaturan global, akun, peran, dan izin sesuai kewenangannya.
- **Pimpinan** membaca dashboard dan laporan sesuai cakupan organisasi yang diberikan. **Karyawan** melihat aset yang ditugaskan langsung kepadanya.
- **Pemindai QR tanpa login** dapat melihat kode aset, nama, kategori, dan status umum.

## Product Purpose

Asset Management System (AMS) membantu satu perusahaan dengan banyak cabang mengetahui lokasi fisik dan penanggung jawab setiap unit aset tetap atau inventaris nonkapital. Target awal di bawah 10.000 unit. MVP berhasil ketika setiap aset aktif memiliki kode unik, QR yang dapat dipindai, lokasi fisik, dan tepat satu penanggung jawab, perubahan penting dapat diaudit, serta daftar, laporan, dan ekspor mematuhi cakupan data pengguna.

## Positioning

AMS melacak lokasi fisik dan penanggung jawab sebagai dua informasi yang berdiri sendiri. Setiap unit memiliki kode dan QR yang tetap sama sepanjang siklus hidupnya, sehingga petugas dapat mengenali unit yang sama ketika lokasi atau penanggung jawab berubah.

## Operating Context

- Registrasi manual menghubungkan unit dengan jenis aset, lokasi fisik, dan satu penanggung jawab berupa karyawan, departemen, atau lokasi. Petugas berizin dapat meninjau dan mengubah status awal **Tersedia** dan kondisi awal **Baik**.
- Di lapangan, petugas memindai QR dari perangkat seluler atau mencari kode aset secara manual ketika kamera tidak dapat digunakan. Detail aset dan riwayat memerlukan login dan pemeriksaan akses.
- Impor Excel/CSV memakai pratinjau dengan pilihan buat, perbarui, atau lewati sesuai izin. Batasnya 1.000 baris dan 10 MB per berkas. Hasil dicatat per baris agar kegagalan satu baris tidak menghalangi baris lain dan baris gagal dapat dicoba ulang secara eksplisit.
- Perubahan lokasi, penanggung jawab, status, dan kondisi dapat ditinjau sebagai kejadian dengan waktu, aktor, alasan, serta nilai sebelum dan sesudah. Laporan kondisi terkini mendukung filter tanggal pembelian, registrasi, atau perubahan terakhir. Ekspor XLSX/CSV memakai cakupan yang sama dengan tampilan.

## Capabilities and Constraints

- MVP mencakup organisasi, lokasi dan kategori bertingkat, jenis aset dengan atribut dinamis, karyawan dan riwayat organisasi, penugasan pejabat efektif, pengelolaan akun dan akses, aset dan lampiran, QR dan label, impor, audit, notifikasi dalam aplikasi, dashboard, serta laporan. Login memakai NIK atau email dan kata sandi, dengan 2FA opsional.
- Otorisasi menggabungkan izin, cakupan data organisasi yang eksplisit, dan Policy sumber daya. Cakupan operasional aset mengikuti cabang lokasi fisiknya. Penugasan langsung memberi karyawan akses baca personal sesuai aturan akses. Detail QR, pencetakan, ekspor, dan pekerjaan latar belakang juga memeriksa akses.
- Aset aktif memiliki tepat satu penanggung jawab aktif. Deaktivasi mengakhiri penugasan aktif, dan reaktivasi membuat penugasan baru sambil mempertahankan kode dan QR. Status operasional, kondisi fisik, dan siklus hidup merupakan informasi yang berbeda.
- Di luar MVP adalah alur persetujuan registrasi dan mutasi, peminjaman, pemeliharaan, stock opname, penyusutan, integrasi HRIS, rekonstruksi posisi historis pada tanggal tertentu, penghapusan permanen aset, dan notifikasi eksternal.

## Evidence on Hand

- Aturan bisnis dan batas MVP dibekukan pada 6 November 2026. [Requirements Baseline v1.0](docs/baseline/REQUIREMENTS_BASELINE_v1.0_APPROVED.md)
- Aktor, alur kerja, ID persyaratan, dan kriteria penerimaan. [PRD AMS v1.0](docs/baseline/PRD_AMS_v1.0_APPROVED.md)
- Model data dan batas transaksi. [ERD AMS v1.0](docs/baseline/ERD_AMS_v1.0_APPROVED.md)

## Product Principles

1. **Dahulukan pekerjaan lapangan.** Saat harus memprioritaskan alur, utamakan registrasi per unit dan pemindaian QR oleh Petugas Aset.
2. **Jaga identitas unit sepanjang siklus hidup.** Kode dan QR tetap, dengan riwayat perubahan yang dapat ditelusuri.
3. **Bedakan lokasi fisik dari tanggung jawab.** Setiap perubahan memiliki tindakan dan riwayat tersendiri.
4. **Jaga konsistensi akses.** Daftar, detail, dashboard, laporan, cetak, dan ekspor mengikuti izin dan cakupan yang berlaku. QR publik terbatas pada empat informasi yang diizinkan.

## Accessibility & Inclusion

- Dukung desktop, tablet, dan ponsel, termasuk pemindaian QR dan pencarian kode secara manual. Pastikan navigasi papan ketik, fokus terlihat, label yang jelas, dan kontras yang memadai pada tema terang dan gelap.
- Sediakan antarmuka, navigasi, dan pesan validasi dalam bahasa Indonesia (`id`, bawaan), Inggris (`en`), dan Tionghoa Sederhana (`zh-CN`). Pertahankan data master sebagaimana dimasukkan pengguna.
