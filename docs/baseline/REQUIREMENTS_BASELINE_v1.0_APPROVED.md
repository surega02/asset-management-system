# Asset Management System — Requirements Baseline

**Versi:** 1.0 Approved / Frozen  
**Tanggal:** 2026-11-06  
**Status:** **APPROVED / FROZEN**  
**Sumber:** brainstorming sesi 1–6 + Validasi 01–16. Detail identitas keputusan ada di `DECISION_LOG_VALIDASI_01-16_APPROVED.md`.

## 1. Tujuan dan batas produk
Aplikasi manajemen aset greenfield untuk **satu perusahaan, banyak cabang** dengan kapasitas awal di bawah 10.000 aset. Seluruh aset tetap dan inventaris nonkapital dicatat **per unit**. Rilis pertama mengutamakan master, karyawan/akun, registrasi, lokasi fisik, penanggung jawab, QR, dashboard, laporan, ekspor, audit, impor, dan administrasi.

**Stack:** Laravel ^13/PHP 8.3+, MySQL InnoDB, Blade + Livewire + Alpine.js, Tailwind CSS, komponen Blade bergaya shadcn, Policies + Spatie Permission, Database Queue, Pest/PHPUnit, NGINX, Git. Modular monolith; dapat dipasang on-premise/cloud.

## 2. Organisasi, karyawan, akun
- Hirarki **Cabang → Divisi → Departemen → Karyawan**; divisi dan departemen dimiliki cabang masing-masing.
- Karyawan memiliki NIK unik tidak dipakai ulang, nama, divisi/departemen, jabatan, lokasi, status, tanggal mulai bekerja dan riwayat perpindahan. Masa kerja dihitung dari tanggal mulai, bukan data manual yang mudah usang.
- Karyawan diinput manual atau impor Excel/CSV; akun login opsional, maksimal satu per karyawan. Email login opsional tetapi unik jika terisi. Login menggunakan NIK atau email + password. Reset lewat email bila tersedia atau mekanisme admin berizin yang aman; 2FA opsional.
- Pindah unit diperbolehkan saat memegang aset: histori lama/baru dan peringatan daftar aset; assignment serta lokasi fisik tidak berubah otomatis. Karyawan **tidak dapat dinonaktifkan** selama masih ada assignment langsung yang aktif.
- HOD: scope departemen; Director: divisi/cabang/perusahaan. Satu pejabat boleh memimpin beberapa unit. Penugasan definitif/Acting memiliki tanggal efektif. Maksimal satu definitif dan satu Acting per unit/peran/waktu; ketika overlap Acting menjadi efektif, kemudian kembali ke definitif bila masih berlaku.

## 3. Hak akses dan keamanan
- Role standar (Super Admin, Admin Aset, Petugas Aset, Pimpinan, Karyawan) dapat diubah/diperluas melalui role dan permission. **Hanya Super Admin** boleh mengubah pengaturan aplikasi global.
- Otorisasi = **permission + data scope + kebijakan resource**. Scope berdasarkan cabang/divisi/departemen; petugas cabang mengakses/mengelola aset menurut **cabang yang menaungi lokasi fisik aset**, bukan cabang penanggung jawab; perpindahan fisik memengaruhi hak akses berikutnya.
- Staf hanya boleh melihat aset yang ditugaskan **langsung** kepadanya; penugasan aset ke departemen/lokasi tidak otomatis membuat aset terlihat sebagai “aset saya”. Pimpinan memperoleh ringkasan sesuai scope yang diberikan, bukan cakupan tak terbatas hanya karena gelar.
- Otorisasi diterapkan pada daftar, detail, perubahan, QR terautentikasi, dashboard, laporan, pencetakan, dan ekspor. Data scope dicek ulang di eksekusi queue; jangan mengandalkan menu UI.
- Reset password oleh admin tidak menampilkan password lama. Tindakan sensitif diaudit, tidak mencatat secret/token/password di audit.

## 4. Master dan registrasi aset
- `asset_code` global otomatis `AST-000001` dan tidak pernah dipakai ulang; setiap aset memiliki token QR publik acak stabil, termasuk setelah reaktivasi.
- Master kategori dan lokasi mendukung parent-child bertingkat; jenis aset dapat mendefinisikan atribut dinamis bertipe dan tervalidasi.
- **Wajib saat registrasi**: nama aset, kategori/jenis, lokasi fisik, satu penanggung jawab employee/departemen/lokasi, status operasional, kondisi fisik, tanggal pembelian, harga perolehan. Status default **Tersedia** dan kondisi default **Baik**, keduanya bisa diubah petugas berizin. Kode dan token QR otomatis.
- **Opsional**: vendor, nomor invoice, foto, serial number, lampiran invoice. Garansi dan merek disediakan sebagai atribut bila relevan. Nilai keuangan tidak negatif; keputusan semantik tanggal/harga yang tidak diketahui perlu mengikuti kebijakan validasi wajib (tidak diisi = ditolak, tanpa pengecualian yang disepakati).
- Registrasi langsung tercatat tanpa approval oleh pengguna berizin, baik manual maupun impor.
- Lifecycle `is_active` **independen** dari status operasional dan kondisi. Status terakhir pada aset nonaktif bersifat informatif, bukan izin operasi.

## 5. Lokasi, assignment, dan lifecycle
- Setiap aset aktif **tepat satu** penanggung jawab aktif: karyawan **atau** departemen **atau** lokasi. Tidak boleh kombinasi. Lokasi tanggung jawab dapat berbeda dari lokasi fisik.
- Pergantian lokasi fisik tidak mengubah penanggung jawab; pergantian penanggung jawab tidak mengubah lokasi. Setiap tindakan mencatat aktor, timestamp, alasan dan nilai before/after, histori dipertahankan.
- Deaktivasi aset menutup assignment aktif dalam satu transaksi dengan perubahan lifecycle, alasan wajib, lampiran opsional, audit; aset nonaktif harus memiliki **nol** assignment aktif. Hard delete tidak tersedia.
- Reaktivasi mewajibkan assignment baru dan status yang sah, mempertahankan histori lama serta kode dan QR yang sama.

## 6. Impor
- Impor karyawan dan aset manual/Excel/CSV; untuk impor aset: preview sebelum commit, deteksi kemiripan/duplikat, pemilihan create/update/skip sesuai permission. `asset_code` dicocokkan untuk update; kosong = kandidat aset baru; kode diberikan saat commit.
- **Partial success + atomic per row**: kegagalan satu baris tidak membatalkan yang lain; status sukses/gagal/skip dan alasannya dicatat.
- **Idempotency per row + resume**: identitas batch+nomor baris stabil, sukses tidak boleh diproses ulang, pending dapat dilanjutkan, gagal dapat diulang eksplisit setelah koreksi dan revalidasi, worker terputus direkonsiliasi. Aset, audit, dan status sukses satu transaksi.
- Batas 1.000 baris/batch dan file 10MB. Hasil impor yang bersifat sementara disimpan 90 hari; audit bisnis tetap dipertahankan.

## 7. QR, dashboard, laporan, dan UI
- QR publik **hanya** kode aset, nama, kategori dan status umum; data pribadi/finansial/lokasi rinci tidak dibuka. Informasi lengkap dan histori setelah login **serta lolos Policy dan scope**. Label dapat dicetak satuan/massal, ukuran dan field dari daftar aman dapat dikonfigurasi.
- Dashboard analitik berbasis role/scope dan ringkasan aset yang langsung ditugaskan kepada user. Laporan posisi **terkini** dan rekap per cabang/departemen/karyawan/kategori/kondisi, dapat memilih filter jenis tanggal pembelian/registrasi/perubahan terakhir.
- Laporan histori: daftar kejadian perubahan lokasi, assignment, status, kondisi per periode dengan before/after, aktor, waktu dan alasan. **As-of reporting tidak masuk MVP**. XLSX/CSV mengikuti scope yang sama.
- Responsive desktop/tablet/mobile, mobile untuk scan QR/petugas, light/dark, bahasa UI Indonesia (`id`, default), English (`en`), Simplified Chinese (`zh-CN`); data master bebas tidak diterjemahkan otomatis.

## 8. Operasional
- Session idle 30 menit; 5 percobaan login per menit per pasangan akun/IP; 2FA opsional.
- Foto JPG/PNG/WebP maks. 5MB, invoice PDF/JPG/PNG maks. 10MB; file validasi konten server-side.
- Database queue default. Audit log tidak auto-purge pada MVP. Backup database harian terenkripsi retensi 30 hari, target RPO 24 jam dan RTO 8 jam, wajib uji pemulihan sebelum produksi.
- Notifikasi in-app: hasil impor kepada pelaksana; pindah karyawan yang masih memegang aset kepada admin terkait; deaktivasi/reaktivasi kepada admin terkait; perubahan assignment kepada pihak lama/baru bila berakun dan berizin; Plt. mendekati selesai kepada Super Admin.

## 9. Batas MVP
Tidak termasuk: workflow approval mutasi, peminjaman/pengembalian formal, maintenance, stock opname, penyusutan, rekonstruksi posisi historis as-of, integrasi HRIS, email/WhatsApp/push notification, dan hard-delete aset.

## 10. Pembekuan
Status **Approved / Frozen** sejak persetujuan pemilik produk pada 2026-11-06. Migration final dan sprint implementasi mengacu baseline ini, dengan review teknis tetap wajib. Perubahan setelah persetujuan melalui change request bernomor dan impact assessment PRD/ERD/tests.
