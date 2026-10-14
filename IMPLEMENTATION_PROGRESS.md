# Progres implementasi PRD Sistem Informasi Martabak

Stack: CodeIgniter 4.7.5, PHP 8.3, Tailwind CSS 4, MySQL, Bahasa Indonesia, zona waktu Asia/Jakarta.

## Tahap 1 — Fondasi

- [x] Scaffold CodeIgniter 4 dan struktur MVC
- [x] Koneksi MySQL berbasis environment
- [x] Migrasi tabel akun owner/kasir
- [x] Login/logout, hash password, rotasi sesi, dan pemeriksaan status akun
- [x] Pembatasan percobaan login per IP dan identitas
- [x] Filter autentikasi dan role owner pada route server
- [x] Owner membuat, menonaktifkan, mengaktifkan, dan mereset akun kasir
- [x] Layout panel bernuansa kuning dan halaman publik awal
- [x] Pipeline Tailwind lokal
- [x] Instal dependency Composer dan npm; lockfile tersimpan
- [x] Build CSS Tailwind lokal
- [x] Tambahkan proses pembuatan owner pertama yang aman
- [x] Jalankan migrasi MySQL lokal (tabel `users` dibuat)
- [x] Verifikasi HTTP halaman utama, halaman login, CSS, dan pengalihan dashboard saat belum login
- [ ] Buat akun owner dengan kredensial pilihan pemilik

## Tahap 2 — Master dan publik

- [x] Migrasi MySQL kategori, produk, dan varian
- [x] Pengelolaan kategori, produk, varian awal, harga, unggulan, dan status publik
- [x] Katalog publik dinamis dengan filter kategori dan keadaan kosong
- [x] Sistem visual responsif berdasarkan `DESIGN.md`, termasuk drawer panel dan navigasi keyboard
- [x] Master bahan dengan satuan dasar, batas minimum, dan konversi pembelian eksplisit
- [x] Master supplier dan status aktif/nonaktif
- [x] Resep per varian berbasis bahan dan takaran satuan dasar
- [x] Foto produk JPEG/PNG/WebP maksimal 2 MB dan maksimal 5 foto per produk
- [x] Pencarian katalog dan halaman detail produk
- [x] Banner terjadwal dengan pratinjau, validasi URL, dan upload maksimal 3 MB
- [x] Pengaturan nama usaha, alamat, kontak, serta jam buka
- [x] Topping, tambahan harga, status, dan pemakaian bahan topping
- [x] Form edit kategori, produk, varian, bahan, supplier, topping, dan metadata banner

## Tahap 3 — Operasional

- [x] Draft penerimaan, item dengan konversi satuan, posting atomik, biaya rata-rata, dan kartu stok
- [x] Shift, POS, pembayaran gabungan, struk cetak, dan perlindungan transaksi ganda
- [x] Refund parsial/penuh per item, alokasi nilai, persetujuan owner, riwayat, dan dampak ke kas shift

## Tahap 4 — Kontrol keuangan

- [x] Sesi stok opname, snapshot cut-off, penguncian mutasi, hitung fisik, persetujuan owner, dan posting selisih
- [x] Jurnal selisih opname otomatis
- [x] Lampiran bukti stok opname privat dengan validasi isi dan batas ukuran
- [x] Kategori dan pengeluaran dengan alur draft, pengajuan, persetujuan, pembayaran, bukti privat, dan sumber laci/kas/bank
- [x] Daftar akun, jurnal otomatis berpasangan, saldo awal, buku besar, neraca saldo, laba rugi, neraca, laporan kas, dan tutup periode

## Tahap 5 — Pelaporan dan ulasan

- [x] Dashboard owner/kasir dengan metrik penjualan, laba terbatas role, kas/bank, shift, stok menipis, dan produk terlaris
- [x] Laporan penjualan harian/bulanan/tahunan dengan filter, detail, rekap, dan PDF A4 berbasis snapshot data yang sama
- [x] PDF stok/mutasi, opname, pengeluaran, shift, dan keuangan periodik sesuai hak akses role
- [x] Token ulasan 256-bit tersimpan sebagai hash, kedaluwarsa 30 hari, pembatasan percobaan, honeypot, dan verifikasi adaptif
- [x] Ulasan per item transaksi, maksimal 3 foto tanpa metadata, moderasi owner, rating publik, dan penanganan refund penuh

## Tahap 6 — Stabilisasi

- [x] Paginasi laporan transaksi/refund, ringkasan berbasis batch, dan batas rincian PDF
- [x] Konfigurasi base URL, redirect HTTPS, cookie Secure, header keamanan, dan proxy tepercaya eksplisit
- [x] Penanda konten demo, penyaringan materi contoh dari publik, dan label progres dashboard
- [x] Panduan build/deploy, akun owner/kasir, backup, retensi, restore, dan checklist UAT di `docs/DEPLOYMENT-UAT.md`
- [x] Suite verifikasi lokal pada MySQL terisolasi: 22 tes, 234 assertion
- [x] Benchmark laporan 100.000 transaksi dan batas ekspor 5.000 detail
- [ ] Jalankan seluruh AC-01–AC-25 dan catat hasil UAT per role
- [ ] Screenshot desktop/mobile untuk halaman prioritas
- [ ] Verifikasi domain, sertifikat, proxy, cookie, dan tautan QR pada deployment HTTPS nyata
- [ ] Pulihkan backup produksi di lingkungan uji; rekonsiliasi transaksi, jurnal, stok, dan lampiran serta ukur RPO/RTO
