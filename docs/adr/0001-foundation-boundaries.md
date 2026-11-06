# ADR 0001 — Batas fondasi M01

Status diterapkan pada M01.

Tabel antrean Laravel dapat dibuat sejak M01 karena ketiganya berdiri sendiri. Migrasi identitas, scope, dan audit bisnis mengikuti urutan dependensi ERD pada milestone domain. Sesi lokal tetap memakai penyimpanan file dengan idle timeout 30 menit.

Shell internal dirender dari dashboard sintetis yang dibatasi ke lingkungan lokal dan pengujian. Halaman publik menyediakan layout guest. Autentikasi dan persistensi preferensi per akun mengikuti M02.

Kontrol bahasa dashboard berjalan di dalam komponen Livewire yang juga merender shell. Ini mempertahankan filter, draf, dan hasil pencarian saat bahasa berubah. Preferensi bahasa dibaca dari sesi pada setiap request. Tampilan publik memakai pengiriman form Laravel agar pilihan bahasa juga dapat disimpan tanpa JavaScript.
