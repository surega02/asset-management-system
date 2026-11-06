# Product Requirements Document (PRD) — Asset Management System

**Versi:** 1.0 Approved / Frozen | **Tanggal:** 2026-11-06  
**Status:** APPROVED / FROZEN  
**Sumber kebenaran:** `REQUIREMENTS_BASELINE_v1.0_APPROVED.md` + `DECISION_LOG_VALIDASI_01-16_APPROVED.md`  
**Model data:** `ERD_AMS_v1.0_APPROVED.md`

## 1. Ringkasan, tujuan, dan keberhasilan
AMS adalah aplikasi greenfield untuk satu perusahaan dengan banyak cabang, mengelola aset tetap dan inventaris nonkapital per unit. MVP memungkinkan registrasi, pelacakan lokasi fisik dan penanggung jawab, pengelolaan karyawan/akses, QR, impor, riwayat, dashboard, dan laporan yang aman.

**Sasaran keberhasilan MVP:** setiap aset aktif mempunyai satu kode unik, QR yang dapat dipindai, lokasi fisik, dan tepat satu penanggung jawab; perubahan penting dapat diaudit; registrasi dan impor tidak melewati otorisasi; daftar/laporan/ekspor tidak melintasi data scope pengguna; backup/restore dan tes kritis lulus.

## 2. Ruang lingkup
**P1 / In scope:** master organisasi, lokasi, kategori/jenis/atribut; karyawan dan histori; akun, login email/NIK, 2FA opsional, role/permission/scope; aset/attachment/assignment, perubahan/deaktivasi/reaktivasi; QR+label; impor preview/partial/retry; audit, in-app notifications; dashboard; laporan terkini dan event; XLSX/CSV; i18n id/en/zh-CN, tema light/dark, mobile.

**Out of scope:** HRIS, approval registrasi/mutasi, mutasi formal dengan workflow, peminjaman, maintenance, penyusutan, stock opname, as-of report, hard delete, notifikasi eksternal. Struktur histori disiapkan agar modul masa depan dapat ditambahkan tanpa mengubah identitas aset.

## 3. Aktor dan pembatasan akses
| Aktor | Aktivitas inti | Batasan |
|---|---|---|
| Super Admin | Konfigurasi global; role/permission; pengguna; semua modul sesuai hak sistem | Aksi diaudit; tidak boleh melihat password/2FA secret |
| Admin Aset | Master dan aset sesuai permission+scope; laporan | Akses lintas cabang hanya jika scope eksplisit |
| Petugas Aset | Registrasi, update, import, label dan perpindahan lokal | **Cabang lokasi fisik aset** menentukan apakah aset masuk scope petugas, bukan cabang employee |
| Pimpinan | Dashboard/laporan sesuai scope organisasi | Tidak memperoleh akses global hanya karena title |
| Karyawan | Melihat aset yang langsung di-assign ke diri sendiri | Aset departemen/lokasi bukan otomatis “aset saya” |

**Aturan otorisasi `BR-IAM-016`:** bila aset secara fisik di Jakarta dan karyawan penanggung jawab berada di Bandung, petugas dengan scope Jakarta dapat bertindak bila mempunyai permission yang sesuai; petugas Bandung tidak otomatis boleh mengelola aset itu. Perubahan lokasi fisik wajib memeriksa hak tindakan pada aset sumber dan hak memilih lokasi tujuan; sesudah commit, pembacaan berikutnya mengikuti scope lokasi baru. Tanggung jawab pribadi karyawan tetap memberi hak baca “aset saya” yang dibatasi pada record langsung, tidak otomatis hak edit. Detail QR setelah login, ekspor, pekerjaan queue, dan notifikasi memakai aturan yang sama.

## 4. Functional requirements dan acceptance criteria

| ID | Prioritas | Requirement | Acceptance criteria terukur |
|---|---|---|---|
| ORG-001 | P1 | Struktur Cabang→Divisi→Departemen | Unit tidak dapat dihubungkan ke induk pada cabang salah; unit yang dipakai tidak di-hard-delete |
| ORG-002 | P1 | Penugasan HOD/Director definitif dan Acting dengan scope & periode | HOD hanya departemen, Director divisi/cabang/company; Acting prioritas pada tanggal overlap; dua sejenis overlap ditolak, termasuk request paralel |
| EMP-001 | P1 | CRUD karyawan + NIK unik | NIK kosong/duplikat ditolak; NIK tidak didaur ulang; masa kerja dari tanggal bergabung |
| EMP-002 | P1 | Perpindahan karyawan dengan histori | Pindah cabang diizinkan walau ada aset; peringatan tampil; assignment tidak berubah; old/new dan alasan tersimpan |
| EMP-003 | P1 | Cegah penonaktifan karyawan pemegang aset | Tidak aktif ditolak jika ada assignment langsung aktif, termasuk request bersamaan |
| IAM-001 | P1 | Karyawan opsional mempunyai satu akun | `employee_id` user unik; email nullable+unik; login NIK/email; akun nonaktif ditolak |
| IAM-002 | P1 | Reset password aman + 2FA opsional | Admin tidak mengetahui password; reset diaudit; recovery codes dilindungi; rate-limit dan session timeout diterapkan |
| IAM-003 | P1 | Role/permission/scope | Query + Policy memfilter aset berdasarkan physical branch; pegawai hanya melihat assignment sendiri; akses URL/ekspor lintas scope ditolak |
| MST-001 | P1 | Kategori dan lokasi hierarkis | Parent-child tidak boleh siklus, tidak boleh lintas cabang untuk anak lokasi; data master terpakai tidak dihapus permanen |
| MST-002 | P1 | Jenis aset + atribut dinamis | Atribut sesuai jenis, tipe data dan mandatory validation diterapkan pada form/import; nilai lama tidak hilang ketika definisi diarsipkan |
| AST-001 | P1 | Registrasi aset per unit | Field wajib nama, kategori/jenis, lokasi, assignment, status, kondisi, tanggal pembelian, harga; kode/QR unik otomatis; default Tersedia/Baik editable |
| AST-002 | P1 | Lampiran & atribut opsional | Serial, vendor, invoice, foto, file invoice opsional; tipe dan ukuran file diverifikasi; file pribadi tidak tampil publik |
| AST-003 | P1 | Lifecycle terpisah dari status/kondisi | Ubah status/kondisi tidak mengubah `is_active`; aset nonaktif tidak bisa aktivitas baru walau status terakhir Digunakan |
| ASN-001 | P1 | Tepat satu assignment aktif untuk aset aktif | Buat aset tanpa assignment ditolak dan rollback; ganti assignment atomik; tepat satu FK target; concurrency tidak menghasilkan ganda |
| ASN-002 | P1 | Lokasi fisik independen dari assignment lokasi | Update lokasi fisik tidak mengubah lokasi penanggung jawab; update assignment tidak mengubah lokasi fisik; keduanya diaudit |
| AST-004 | P1 | Deaktivasi dan reaktivasi | Deaktivasi menutup assignment, alasan wajib, dokumen opsional; reaktivasi wajib assignment baru, kode/QR stabil, transaksi rollback saat gagal |
| QR-001 | P1 | QR publik minimal dan detail terlindungi | Publik hanya kode, nama, kategori, status umum; tidak ada nama employee, NIK, harga, lokasi rinci/serial; detail login + Policy |
| QR-002 | P1 | Cetak label satuan/massal | Template ukuran dan field allowlisted; petugas tidak mencetak aset di luar scope |
| IMP-001 | P1 | Impor preview/create/update/skip dengan deteksi duplikat | Kode ada = kandidat update; kosong = create; kemiripan ditandai bukan ditolak otomatis; pilihan aksi mengikuti izin; batas 1000 rows/10MB |
| IMP-002 | P1 | Partial success/atomic per row | 1 baris gagal tidak rollback baris sukses; aset+assignment+audit+status sukses atomik; hasil lengkap dapat ditinjau |
| IMP-003 | P1 | Idempotency dan resume | `(batch,row_number)` unik; sukses tidak diproses ulang; pending dilanjutkan; gagal retry eksplisit; worker crash direkonsiliasi tanpa duplikat |
| AUD-001 | P1 | Audit business actions | Semua create/update/move/assign/deactivate/role change menyimpan actor, timestamp, reason, old/new aman; password/token tak tersimpan |
| RPT-001 | P1 | Dashboard role/scope aware | Total/rekap tidak menghitung aset di luar scope; “aset saya” hanya direct employee assignment |
| RPT-002 | P1 | Daftar/rekap terkini dan filter | Pilihan basis tanggal pembelian, registrasi, perubahan terakhir; rekap cabang/dept/karyawan/kategori/kondisi; XLSX/CSV identik hasil scoped query |
| RPT-003 | P1 | Laporan event per periode | Perubahan fisik/assignment/status/kondisi menampilkan tanggal, actor, alasan, old/new; tidak menyajikan posisi as-of |
| NTF-001 | P1 | In-app notifications | Enam kategori kejadian yang disepakati; hanya target berizin, tidak bocor payload lintas scope |
| I18N-001 | P1 | Lokal UI + light/dark + mobile | UI id/en/zh-CN; ID default; mobile QR; string UI ditranslasikan; data master tidak diterjemahkan otomatis |
| OPS-001 | P1 | Operasi aman & portable | Batas upload, idle timeout, rate-limit, backup harian+restore drill, queue database, on-prem/cloud |

## 5. Alur inti dan aturan bisnis
### 5.1 Registrasi manual
1. Petugas login, melalui permission dan scope lokasi fisik tujuan.
2. Isi field wajib, pilih penanggung jawab employee **atau** dept **atau** location. Default Tersedia/Baik dapat diubah.
3. Server memvalidasi master, data scope, tipe atribut, harga >= 0, tanggal, upload; transaksi menyimpan nomor unik, aset, assignment, histori awal, audit.
4. QR publik dihasilkan dari token unik stabil dan label dapat dicetak. Jika salah satu perubahan database gagal, tidak ada aset/assignment yang ter-commit sebagian.

### 5.2 Perubahan assignment/lokasi
Perubahan assignment menutup record lama dan membuka yang baru secara atomik, tetap tepat satu assignment pada akhir commit. Perubahan lokasi mengubah `physical_location_id` dan menulis histori tersendiri; tidak menyentuh assignment. Keduanya wajib alasan dan aktor. UI harus meminta konfirmasi bila perpindahan memengaruhi cabang scope, serta memeriksa hak atas lokasi sumber dan tujuan.

### 5.3 Lifecycle
Aksi deactivate: permission + data scope + alasan; lock aset; tutup assignment, `is_active=false`, catat alasan/dokumen dan audit pada transaksi sama. Reactivate: lock aset nonaktif, validasi status pilihan, buat assignment baru (tidak membuka histori), `is_active=true`, audit; kode aset/token tidak berubah.

### 5.4 Impor dan retry
Preview memeriksa struktur file, data master, kemungkinan duplikat, dan pilihan per baris. Tidak menerbitkan asset code. Saat submit, worker melakukan revalidasi serta permission/scope sebelum commit setiap row. Baris sukses final; baris gagal boleh dikoreksi dan retry melalui attempt baru; pending/stale processing perlu direkonsiliasi. Perubahan aset + audit + status result row dipersist sebagai satu transaksi; batch progress diperbarui idempotently. Operasi create/update yang dikirim ulang tidak boleh menghasilkan dua aset/dua audit sukses.

### 5.5 Laporan
**Terkini:** posisi dan assignment yang ada sekarang, dengan filter pembelian/registrasi/perubahan terakhir. **Event:** kejadian yang timestamp-nya dalam rentang periode, bukan snapshot kondisi pada tanggal terpilih. Export menggunakan query yang sama dengan tampilan dan memeriksa ulang akses pada waktu job dieksekusi.

## 6. Parameter nonfungsional & operasional
| Area | Baseline MVP |
|---|---|
| Skala | < 10.000 aset |
| Impor | <=1.000 baris, <=10 MB/file |
| Upload | Foto JPG/PNG/WebP <=5MB, invoice PDF/JPG/PNG <=10MB |
| Auth | idle timeout 30 menit; 5 login gagal per menit per pasangan akun/IP; 2FA opsional |
| Audit | append-only secara aplikasi, tanpa automatic purge MVP |
| Retensi impor | 90 hari untuk file/hasil sementara; audit bisnis tetap |
| Backup | terenkripsi harian, retensi 30 hari; target RPO 24 jam dan RTO 8 jam, wajib uji restore |
| Queue | database queue; Redis opsional nanti berdasarkan kebutuhan |
| UI | responsif, tema terang/gelap, aksesibilitas dan mobile QR; id/en/zh-CN |
| Security | server validation, tenant boundary perusahaan tunggal, deny-by-default, private storage, CSRF, XSS-safe rendering |

## 7. Acceptance test lintas modul (wajib sebelum sign-off development)
| Test ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| T-001 | Dua request create aset serentak | Kode unik; tidak ada assignment ganda |
| T-002 | Registrasi gagal setelah aset dibuat namun sebelum assignment | Semua rollback; tidak tersisa aset tanpa assignment |
| T-003 | Dua petugas mengganti assignment yang sama bersamaan | Maksimal satu assignment aktif, histori konsisten |
| T-004 | Deactivate gagal saat menyimpan audit | Aset tetap aktif dan assignment tidak tertutup |
| T-005 | Reactivate tanpa assignment baru | Ditolak dan tidak ada state parsial |
| T-006 | Karyawan Bandung memegang aset fisik Jakarta | Karyawan dapat membaca miliknya; petugas Bandung tanpa scope Jakarta tidak bisa mengelola/export; petugas Jakarta berizin bisa |
| T-007 | Aset pindah Jakarta ke Bandung | Otorisasi sumber+tujuan dicek; setelah commit scope baca mengikuti Bandung |
| T-008 | Satu definitif + Acting overlap, dua Acting overlap | Yang pertama valid dengan Acting efektif; yang kedua ditolak termasuk concurrent submit |
| T-009 | Import worker mati sesudah commit row | Resume tidak membuat aset/audit kedua; row sukses dikenali |
| T-010 | Import 100: 92 berhasil, 5 gagal, 3 skip | 92 bertahan, error tercatat, skipped tidak berubah |
| T-011 | QR scan tanpa login | Tepat 4 atribut publik, tidak ada PII/keuangan/scope detail |
| T-012 | Export sebagai user tanpa scope | Hasil tidak berisi aset lintas cabang, termasuk saat data berpindah saat job menunggu |
| T-013 | Event bulan Maret, perubahan posisi Juni | Event Maret hanya kejadian Maret; daftar terkini menampilkan data terbaru |
| T-014 | Akun tanpa email login pakai NIK | Berhasil dengan password benar; email kosong tidak menghambat; 1 akun per employee |
| T-015 | Aset baru default status/kondisi | Tersedia/Baik terpilih, petugas bisa mengganti sebelum simpan |

## 8. Notifikasi MVP
1. Impor selesai/gagal → pemilik batch.
2. Transfer organisasi karyawan dengan aset → admin aset terkait.
3. Deaktivasi → admin aset terkait.
4. Reaktivasi → admin aset terkait.
5. Reassignment → pemegang lama/baru bila memiliki akun dan berizin.
6. Acting mendekati selesai → Super Admin.
Notifikasi in-app saja; kontrol scope pada query dan payload.

## 9. Non-goals, asumsi teknis, dan review
- Hanya satu perusahaan, maka scope Company pada penugasan Director mengacu entitas perusahaan tunggal secara logis; tabel `companies` tidak wajib pada MVP (*proposed*).
- Penyimpanan nilai keuangan sebagai `DECIMAL(18,2)` dan waktu tersimpan UTC merupakan rekomendasi teknis, bukan keputusan bisnis baru.
- Pengelompokan “kategori/jenis” harus memastikan kategori yang dipilih pada UI konsisten dengan FK `asset_type_id` di database. Jenis aset merupakan leaf/klasifikasi spesifik untuk atribut dinamis; kategori diturunkan dari jenis tersebut.
- Detail kesesuaian migration dan hardening file upload akan dipastikan pada technical design sebelum coding.

## 10. Definition of Done dan change control
MVP dianggap selesai setelah semua P1 & tes T-001..T-015 lulus, migrasi dari database kosong berhasil, backup/restore diuji, audit sensitif tersanitasi, tiga bahasa dan mobile QR diuji. **Dokumen disetujui dan dibekukan pada 2026-11-06**; keputusan perubahan setelah pembekuan harus melalui change request, review dampak ke tabel/Policies/test, dan versi baru.
