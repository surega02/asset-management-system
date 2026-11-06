---
name: Asset Management System
description: Papan informasi fasilitas untuk ruang kerja aset.
colors:
  canvas: "#f4f3ee"
  canvas-dark: "#141c22"
  surface: "#ffffff"
  surface-dark: "#1b262e"
  subtle: "#eeeee7"
  subtle-dark: "#24343c"
  text: "#1e302e"
  text-dark: "#edf3ef"
  muted: "#4e615f"
  muted-dark: "#bacac5"
  border: "#d9dfda"
  border-dark: "#3c5159"
  control-border: "#74857f"
  control-border-dark: "#738b92"
  accent: "#203548"
  accent-dark: "#f1bd58"
  accent-hover: "#2f4b62"
  accent-hover-dark: "#fbd387"
  accent-ink: "#ffffff"
  accent-ink-dark: "#262015"
  focus: "#335f96"
  focus-dark: "#a5c9fa"
  band: "#203548"
  band-dark: "#223c52"
  band-ink: "#ffffff"
  band-muted: "#d3e0e8"
  band-border: "#577084"
  band-border-dark: "#607b91"
  marker: "#f1bd58"
  meter: "#638b80"
  meter-dark: "#83b6a7"
  selection: "#dbe9e3"
  selection-dark: "#385b53"
  good: "#e0eee5"
  good-dark: "#2a4839"
  good-ink: "#255039"
  good-ink-dark: "#d0eedb"
  warning: "#f9edd3"
  warning-dark: "#4c3b23"
  warning-ink: "#714916"
  warning-ink-dark: "#ffe2a5"
  error: "#952f32"
  error-dark: "#ffabae"
typography:
  headline:
    fontSize: "2rem"
    fontWeight: 680
    lineHeight: 1.2
    letterSpacing: "-.025em"
  title:
    fontSize: "1.1875rem"
    fontWeight: 680
    lineHeight: 1.2
    letterSpacing: "-.025em"
  body:
    fontFamily: "system-ui, \"Segoe UI\", \"Microsoft YaHei UI\", sans-serif"
    fontSize: ".875rem"
  metric:
    fontSize: "1.875rem"
    fontWeight: 680
    lineHeight: 1
    letterSpacing: "-.03em"
    fontFeature: "\"tnum\""
  label:
    fontSize: ".75rem"
    lineHeight: 1.3
  asset-code:
    fontFamily: "ui-monospace, monospace"
    fontSize: ".75rem"
    fontWeight: 500
  table-body:
    fontSize: ".8125rem"
rounded:
  track: ".25rem"
  tag: ".375rem"
  control: ".5rem"
  popover: ".625rem"
  panel: ".875rem"
spacing:
  xs: ".25rem"
  sm: ".5rem"
  md: ".75rem"
  lg: "1rem"
  xl: "1.25rem"
  2xl: "1.5rem"
  3xl: "2rem"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-ink}"
    rounded: "{rounded.control}"
    padding: "0 .75rem"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
  button-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
    padding: "0 .75rem"
  button-outline-hover:
    backgroundColor: "{colors.subtle}"
  select-control:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
    padding: "0 .75rem"
  code-field:
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
    padding: "0 .75rem"
  navigation-link:
    textColor: "{colors.muted}"
    rounded: "{rounded.control}"
    padding: "0 .75rem"
  navigation-current:
    backgroundColor: "{colors.subtle}"
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
    padding: "0 .75rem"
  event-tag:
    backgroundColor: "{colors.subtle}"
    typography: "{typography.label}"
    rounded: "{rounded.tag}"
    padding: ".1875rem .375rem"
  event-tag-registered:
    backgroundColor: "{colors.good}"
    textColor: "{colors.good-ink}"
  event-tag-deactivated:
    backgroundColor: "{colors.warning}"
    textColor: "{colors.warning-ink}"
  panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.panel}"
  branch-band:
    backgroundColor: "{colors.band}"
    textColor: "{colors.band-ink}"
    rounded: "{rounded.panel}"
    padding: "1rem"
---

# Design System — Asset Management System

## Overview

**Creative North Star: "Papan Informasi Fasilitas"**

Papan informasi fasilitas modernis membentuk ruang kerja yang mudah dipindai oleh Petugas Aset. Kanvas netral memberi ruang bagi judul, aksi, dan tabel. Pita biru tinta serta penanda kuning membuat konteks lokasi fisik segera terlihat.

Panel berbingkai menata data yang padat. Kontrol berlabel jelas, angka tabular, dan kode monospace membantu petugas berpindah dari tindakan ke pembacaan data. Hierarki ini dipertahankan pada layar sempit dan tema gelap.

**Key Characteristics:**

- Kanvas netral dengan satu pita biru tinta untuk konteks cabang.
- Kontrol berbingkai dengan label terbaca dan fokus yang terlihat.
- Angka tabular, kode monospace, dan label kejadian berbentuk teks.

## Colors

### Primary

- Aksi utama memakai biru tinta di tema terang dan kuning penanda di tema gelap. Gunakan pasangan `accent` dan `accent-ink`, dengan `accent-hover` untuk keadaan sorot.
- Fokus papan ketik memakai biru yang kontras dengan permukaan di masing-masing tema. Warna yang sama dipakai untuk kursor teks pada bidang isian.

### Secondary

- Pita cabang menggunakan biru tinta pada kedua tema, dengan judul putih, keterangan biru pucat, garis pembagi, dan penanda kuning pada ikon lokasi dan pemisah judul.

### Tertiary

- Hijau redup mengisi batang distribusi. Label registrasi menggunakan pasangan hijau, label deaktivasi menggunakan pasangan ambar, dan kesalahan formulir menggunakan merah. Kejadian lain memakai bidang netral.
- Seleksi teks memiliki bidang hijau tersendiri dan mempertahankan warna teks utama.

### Neutral

- Kanvas terang berwarna batu pucat dan panel berwarna putih. Tema gelap membedakan kanvas arang kebiruan, panel, dan bidang redup.
- Teks utama menegaskan nama dan tindakan. Teks pendukung dipakai untuk keterangan, label sekunder, kode, dan waktu. Bidang redup menyatukan judul tabel, navigasi aktif, dan label netral.

**The Context Band Rule.** Gunakan pita biru tinta dengan judul cakupan dan penanda kuning. Tampilkan label bersama angka agar ringkasan dapat dibaca tanpa menebak konteksnya.

## Typography

- Judul halaman (`2rem`) dan judul bagian (`1.1875rem`) berbobot 680, dengan jarak huruf rapat dan pemenggalan baris seimbang.
- Teks antarmuka (`.875rem`) memakai tumpukan sans sistem dengan Segoe UI dan Microsoft YaHei UI sebagai pilihan lanjutan. Paragraf pendukung memakai tinggi baris 1,55.
- Angka ringkasan (`1.875rem`) berbobot 680 dengan tinggi baris 1. Angka jumlah lokasi dan waktu juga memakai angka tabular.
- Isi tabel memakai ukuran `.8125rem`. Label kejadian dan kode aset memakai `.75rem`, dengan wajah monospace untuk kode.

**The Tabular Data Rule.** Gunakan angka tabular untuk jumlah dan waktu. Letakkan kode aset monospace di dekat nama unit.

## Layout

- Konten terpusat dengan lebar maksimum `88rem`. Ruang sisi `1rem` pada layar sempit menjadi `2rem` mulai `40rem`. Antarbagian memakai sela `1.25rem`.
- Di bawah `64rem`, menu lipat berada di bilah atas bersama pemilih bahasa dan tombol tema. Mulai `64rem`, navigasi memakai sidebar melekat selebar `14rem`. Bilah atas memiliki tinggi minimum `4rem`.
- Panel detail bertumpuk sebelum `80rem`. Mulai `80rem`, daftar lokasi dan tabel aktivitas berbagi dua kolom dengan proporsi `0.85fr` dan `1.45fr`. Panel aksi dan judul dapat membungkus, sementara angka ringkasan tetap memakai tiga kolom.
- Sediakan ruang bagi bahasa Indonesia, Inggris, dan Tionghoa Sederhana. Judul ruang kerja, label metrik, dan sel tabel dapat membungkus saat ruang terbatas.

## Elevation & Depth

Lapisan dibentuk dengan perbedaan warna kanvas, panel, dan pita. Panel dan menu lipat memakai garis tipis. Navigasi aktif memakai bidang redup dengan garis bawah setebal 1 px di sisi dalam.

**The Distinct Control Border Rule.** Gunakan garis struktural untuk panel dan pemisah. Gunakan garis kontrol yang lebih tegas pada bidang isian, pilihan, dan tombol berbingkai.

## Shapes

Panel dan pita memakai sudut lembut (`.875rem`). Kontrol dan navigasi memakai sudut lebih kecil (`.5rem`), menu lipat `.625rem`, label kejadian `.375rem`, dan lintasan distribusi `.25rem`. Ikon garis berbagi ketebalan goresan 1,7 pada bidang gambar `24 × 24`.

## Components

Gunakan komponen Blade bersama untuk tombol, panel, ikon, dan navigasi. Nilai pada frontmatter merujuk pada token dan ukuran yang diterapkan oleh [stylesheet AMS](resources/css/app.css).

### Buttons

Tombol Blade memiliki varian berbingkai dan utama. Keduanya setinggi minimal `2.75rem`, dengan ruang sisi `.75rem`. Tombol ikon memakai kotak `2.75rem` dan nama yang dapat diakses. Varian utama memakai pasangan warna aksi, varian berbingkai memakai garis kontrol dan bidang redup saat disorot. Fokus papan ketik memakai garis luar 2 px dengan jarak 3 px. Tombol nonaktif ditampilkan dengan opasitas 65%.

Gunakan `<x-ui.button>` untuk varian berbingkai dan tambahkan `variant="primary"` untuk aksi utama. Tipe bawaannya `button`. Gunakan `type="submit"` untuk mengirim formulir. [Komponen tombol](resources/views/components/ui/button.blade.php)

### Inputs / Fields

Pemilih cabang dan bidang kode memakai garis kontrol 1 px serta label yang terlihat. Bidang kode memberi fokus pada bingkai saat isian menerima fokus. Letakkan pesan validasi di bawah aksi pencarian dan tautkan ke bidangnya. Hasil pencarian dan pesan validasi diumumkan di area hidup yang sopan.

Saat Livewire memproses pencarian, tombol kirim dinonaktifkan dan labelnya menunjukkan proses. Saat cabang berganti, pemilih dinonaktifkan sementara dan status pemuatan diumumkan. [Contoh formulir dashboard](resources/views/livewire/dashboard.blade.php)

### Navigation

Tautan memakai ikon garis dan tinggi minimum `2.75rem`. Sorot dan halaman aktif memakai bidang redup. Tautan aktif membawa `aria-current`. Pada layar sempit, menu lipat menggunakan kontrol asli peramban. Tautan lewati navigasi terlihat ketika menerima fokus.

Sidebar dan menu lipat memakai komponen `<x-layouts.navigation>` yang sama. [Komponen navigasi](resources/views/components/layouts/navigation.blade.php)

### Chips

Label pendek memakai bidang lembut dan selalu memuat teks. Label kejadian registrasi berwarna hijau dan deaktivasi berwarna ambar. Perubahan lokasi, perubahan kondisi, dan status hasil pencarian memakai bidang netral.

### Cards / Containers

Panel menggunakan permukaan tema dan garis struktural 1 px. Isi panel aksi memakai ruang dalam `1rem`, lalu `1.25rem` mulai `40rem`. Panel detail menyelaraskan judul dan isi pada garis tepi yang sama. Tabel mempertahankan header kolom dan baris yang semantik ketika isi sel membungkus.

Gunakan `<x-ui.panel>` untuk panel yang dirender sebagai elemen `section`. Hubungkan `aria-labelledby` ke judul bagian ketika panel perlu dikenali sebagai wilayah tersendiri. [Komponen panel](resources/views/components/ui/panel.blade.php)

### Pita konteks cabang

Pita menampilkan judul cabang, keterangan cakupan fisik, penanda lokasi, dan tiga jumlah berlabel. Saat cabang berganti, judul dan angka tampil bersama. Pita bergerak vertikal 2 px selama 180 ms hanya ketika pengguna mengizinkan gerak. Batang distribusi di panel lokasi menyertai jumlah unit yang tercetak.

## Do's and Don'ts

### Do

- Gunakan label yang tetap terlihat pada bidang kode aset dan tampilkan fokus pada kontrol saat dinavigasi dengan papan ketik.
- Biarkan judul, label, dan isi tabel membungkus saat ruang sempit. Pertahankan ikon garis sebagai pendamping label pada aksi berteks.
- Pertahankan pasangan warna permukaan dan teks sesuai perannya di tema terang dan gelap.

### Don't

- Hindari mengandalkan warna atau panjang batang untuk menyampaikan jenis kejadian dan jumlah unit. Sertakan teks atau angka yang terlihat.
- Hindari memakai garis pembatas panel pada kontrol berbingkai. Gunakan token `control-border`.
- Hindari memakai placeholder sebagai satu-satunya label bidang.
