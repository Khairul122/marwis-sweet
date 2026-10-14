# Deployment, UAT, dan pemulihan

Panduan ini melengkapi [README](../README.md) dan [PRD](../PRD%20(1).md). Jangan menyalin kredensial produksi ke Git, log, atau tiket dukungan.

## Konfigurasi lingkungan

Isi `.env` di mesin deployment. `app.baseURL` harus memakai host publik dan path aplikasi yang benar, diakhiri `/`.

Untuk HTTP lokal atau LAN:

```ini
app.baseURL = 'http://192.168.1.20:8080/'
app.forceGlobalSecureRequests = false
# app.proxyIPs = ''
```

Untuk HTTPS langsung:

```ini
app.baseURL = 'https://martabak.example.id/'
app.forceGlobalSecureRequests = true
# app.proxyIPs = ''
```

Jika TLS berhenti di reverse proxy, masukkan hanya alamat IP/CIDR proxy yang benar-benar dikelola. Nilainya berupa daftar dipisahkan koma, misalnya `app.proxyIPs = '127.0.0.1,10.20.0.0/24'`. Aplikasi hanya mempercayai `X-Forwarded-For` dan skema HTTPS dari alamat tersebut. Jika daftar kosong, header proxy tidak dipakai. Jangan memasukkan rentang internet umum.

Cookie sesi otomatis memakai atribut `Secure` saat skema `app.baseURL` adalah HTTPS. Pengalihan HTTPS hanya aktif jika base URL HTTPS dan `app.forceGlobalSecureRequests = true`; konfigurasi HTTP LAN tetap HTTP. Header `X-Frame-Options`, `X-Content-Type-Options`, `X-Download-Options`, `X-Permitted-Cross-Domain-Policies`, dan `Referrer-Policy` berlaku pada seluruh respons. CSP belum diaktifkan karena beberapa halaman masih memakai skrip/style inline.

Setelah mengubah konfigurasi, periksa halaman login, buat sesi, unggah gambar, finalisasi POS, buka tautan ulasan, dan unduh PDF. Di deployment HTTPS periksa `Set-Cookie: Secure`, sertifikat, tidak ada mixed content, dan tidak ada redirect berulang. Tautan QR/ulasan harus dibentuk dari host `app.baseURL` yang dapat dijangkau pelanggan.

## Build dan deploy

1. Gunakan PHP versi yang tercatat di README, Composer, Node.js, dan MySQL yang mendukung skema aplikasi.
2. Pasang dependency dari lockfile: `composer install --no-dev --classmap-authoritative` dan `npm ci`.
3. Bangun CSS produksi: `npm run build:css`.
4. Siapkan database utf8mb4 dan akun MySQL aplikasi dengan hak minimum yang diperlukan.
5. Isi `.env` di server, termasuk `CI_ENVIRONMENT = production`, `app.baseURL`, kunci enkripsi, konfigurasi MySQL, dan owner awal.
6. Arahkan document root web server ke `public/`; jangan publikasikan root repository atau `writable/`.
7. Jalankan `php spark migrate --all`, buat owner melalui prosedur bootstrap yang tercatat di README, lalu hapus nilai bootstrap dari `.env`.
8. Jangan jalankan `DevelopmentSeeder`, `TemporaryDataSeeder`, `EvaluationDataSeeder`, atau akun demo di production.
9. Uji route publik dan panel melalui hostname produksi sebelum membuka layanan.

## Akun dan operasi harian

- **Owner** mengelola identitas usaha, katalog, hak kasir, kebijakan diskon, persetujuan, jurnal, tutup periode, ulasan, dan laporan finansial.
- **Kasir** memakai akun individual, membuka shift dengan uang awal, mencatat transaksi/refund sesuai hak, lalu menutup shift dengan hitung kas dan alasan selisih.
- Jangan berbagi akun. Nonaktifkan akun petugas yang sudah tidak bertugas dan tinjau laporan/audit setiap akhir periode.
- Ganti data contoh dari panel **Identitas usaha dan banner**. Menyimpan identitas usaha menonaktifkan penanda demo. Pastikan nama, kontak, alamat, jam buka, menu, harga, dan materi promo sudah disahkan sebelum menghapus penanda demo.

## Backup harian

Target PRD saat ini: backup harian, retensi 30 hari, RPO maksimal 24 jam, RTO maksimal 4 jam. Simpan cadangan terenkripsi di luar host aplikasi, batasi akses, dan pantau hasil job.

Siapkan login path tanpa menaruh password di command line:

```text
mysql_config_editor set --login-path=marwis-backup --host=127.0.0.1 --user=backup --password
```

Jalankan dari `cmd.exe` atau scheduler dengan direktori backup yang dibatasi aksesnya:

```text
mysqldump --login-path=marwis-backup --single-transaction --routines --triggers --events --hex-blob --default-character-set=utf8mb4 --result-file=D:\Backups\marwis_sweet.sql marwis_sweet
```

Cadangkan juga upload yang dirujuk database, khususnya `public/uploads/` (gambar produk, banner, ulasan, dan bukti privat). Enkripsi arsip sebelum dipindahkan ke penyimpanan luar host. Retensi: hapus cadangan di luar 30 hari sesuai kebijakan penyimpanan yang disepakati; jangan menghapus satu-satunya cadangan terverifikasi.

## Uji restore dan rekonsiliasi

Pulihkan ke schema dan direktori uji kosong, tidak pernah langsung ke database produksi. Gunakan akun restore terpisah dengan hak hanya pada schema uji.

```text
mysql --login-path=marwis-restore -e "CREATE DATABASE marwis_sweet_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql --login-path=marwis-restore --database=marwis_sweet_restore < D:\Backups\marwis_sweet.sql
```

Ekstrak arsip upload ke direktori aplikasi uji yang terisolasi, lalu set `.env` aplikasi uji agar menunjuk ke schema tersebut. Verifikasi:

1. Aplikasi uji dapat login dan halaman katalog/POS/laporan terbuka.
2. Bandingkan jumlah dan nilai total `sales`, `refunds`, `journal_entries`, `journal_lines`, `stock_movements`, `goods_receipts`, dan `expenses` antara sumber backup dan hasil restore.
3. Pastikan setiap jurnal berstatus posted seimbang: total debit sama dengan total kredit hingga dua desimal.
4. Cocokkan saldo `inventory_items` dengan saldo hasil kartu stok sesuai kebijakan opening balance dan transaksi yang diposting.
5. Pastikan semua `product_images`, `banners`, `review_images`, serta bukti privat yang dirujuk benar-benar ada di salinan upload. Jangan membuka bukti privat melalui route publik.
6. Catat durasi backup/restore, versi MySQL, ukuran berkas, hasil rekonsiliasi, dan waktu pemulihan. Uji ini harus memenuhi RPO/RTO PRD sebelum go-live.

## Catatan UAT Task 23–26

Status yang tercatat pada 14 Oktober 2026:

| Area | Bukti backend | Tampilan | Status |
| --- | --- | --- | --- |
| Laporan penjualan berpaginasi | MySQL feature test memeriksa dua halaman transaksi/refund, filter produk/metode, dan total lengkap dibanding agregat SQL | Feature response merender label rentang dan navigasi transaksi/refund; halaman login juga terbuka di browser lokal | Lulus otomatis pada fixture |
| Batas PDF laporan penjualan | Suite lengkap: 22 tes/234 assertion. Benchmark 100.000 penjualan beserta item/pembayaran cocok dengan `SUM(subtotal)`; PDF dibatasi 5.000 detail, waktu build 27,2 detik, puncak 54 MiB pada komputer uji | PDF fixture 10 halaman A4 dirender; halaman 1, 2, dan 10 diperiksa untuk header tabel, nominal, pergantian halaman, dan footer; screenshot browser route laporan belum tersedia | Lulus pada fixture dan benchmark lokal; target latensi produksi belum ditentukan |
| Batas PDF operasional | Detail lebih dari 5.000 ditolak sebelum dimuat ke template; agregat keuangan tetap memakai SQL | Pesan batas dipulangkan sebagai flash error di panel | Perlu uji fixture besar |
| Base URL, HTTPS, proxy, cookie | Unit test: 2 tes/8 assertion untuk HTTP LAN, HTTPS, proxy valid/invalid, redirect, dan cookie | Login dirender pada browser lokal; domain, sertifikat, dan proxy produksi belum tersedia di sini | Lulus konfigurasi; deployment belum diuji |
| Data contoh | Seeder mengaktifkan penanda demo; profil lama tanpa penanda diperlakukan sebagai demo; banner demo dan review dengan token `EVL contoh` tidak diterbitkan sebagai konten pelanggan | Browser runtime menampilkan notifikasi demo di halaman publik; dashboard menandai Tahap 5 selesai dan Tahap 6 berjalan | Lulus render otomatis dan browser |
| AC-01–AC-25 dan restore | Suite MySQL terisolasi lulus (22 tes/234 assertion); seluruh acceptance criteria dan restore backup produksi belum dijalankan | Halaman login dan publik dirender; PDF laporan diperiksa lewat render PNG; screenshot viewport browser dashboard/POS belum tersedia | Belum tersertifikasi penuh |

Gunakan daftar AC di PRD sebagai checklist pelaksanaan. Untuk setiap AC, catat tanggal, role, schema uji, langkah, hasil yang diharapkan/aktual, tautan bukti, dan status `Lulus`, `Gagal`, atau `Belum diuji`. Prioritas rilis yang belum lulus harus ditutup atau disetujui tertulis oleh owner.
