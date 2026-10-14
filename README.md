# Marwis Sweet — Sistem Informasi Martabak

Source code ini mengikuti urutan tahap pada [PRD](PRD%20(1).md). Status pekerjaan dicatat di [IMPLEMENTATION_PROGRESS.md](IMPLEMENTATION_PROGRESS.md).

## Menjalankan Tahap 1 — Fondasi

1. Pastikan PHP 8.2+, Composer 2.0.14+, Node.js, dan MySQL tersedia.
2. Buat database MySQL bernama `marwis_sweet` dengan charset `utf8mb4`.
3. Salin file `env` menjadi `.env`, lalu sesuaikan `app.baseURL`, kredensial MySQL, dan `BUSINESS_NAME`. Cookie sesi otomatis memakai atribut `Secure` saat `app.baseURL` menggunakan HTTPS. Atur `app.forceGlobalSecureRequests = true` hanya untuk HTTPS; konfigurasi LAN dapat tetap HTTP.
4. Pasang dependency: `composer install` dan `npm install`.
5. Bangun aset CSS lokal: `npm run build:css`.
6. Jalankan migrasi: `php spark migrate`.
7. Isi sementara `BOOTSTRAP_OWNER_NAME`, `BOOTSTRAP_OWNER_USERNAME`, `BOOTSTRAP_OWNER_EMAIL` (opsional), dan `BOOTSTRAP_OWNER_PASSWORD` di `.env`, lalu jalankan `php spark owner:create`. Command menghapus baris bootstrap setelah akun dibuat.
8. Jalankan server lokal dengan `php spark serve`.

Jangan menyimpan rahasia di file `env` yang dilacak Git. File `.env` sudah diabaikan Git. Untuk deployment web, arahkan document root ke folder `public`.

Panduan konfigurasi proxy, deployment, backup, restore, dan UAT ada di [docs/DEPLOYMENT-UAT.md](docs/DEPLOYMENT-UAT.md).

## Seeder

Isi `SEED_OWNER_*` pada `.env`, lalu buat atau perbarui akun permanen dengan:

```bash
php spark db:seed PermanentAccountSeeder
```

Akun kasir permanen bersifat opsional. Isi seluruh `SEED_CASHIER_*` bila dibutuhkan. Seeder akun aman dijalankan ulang berdasarkan username, tetapi password akan disetel ulang ke nilai terbaru dari `.env`.

Untuk data contoh lokal, jalankan:

```bash
php spark db:seed TemporaryDataSeeder
```

Data contoh memakai slug dan SKU berawalan `demo-` atau `DEMO-`, sehingga seeder dapat dijalankan ulang tanpa duplikasi. Jangan menjalankan seeder ini pada database produksi.

Untuk instalasi pengembangan baru, jalankan `php spark db:seed DevelopmentSeeder`. Jika `SEED_OWNER_*` belum diisi, seeder membuat akun lokal berikut:

| Role | Username | Password |
| --- | --- | --- |
| Owner | `owner` | `DemoOwner!2026` |
| Kasir | `kasir` | `DemoKasir!2026` |

Akun demo dan seeder data sementara otomatis ditolak saat `CI_ENVIRONMENT` bernilai `production`. Jika `SEED_OWNER_*` sudah lengkap, `DevelopmentSeeder` memakai akun permanen dari `.env`.

## Urutan pengerjaan

1. Fondasi
2. Master dan publik
3. Operasional
4. Kontrol keuangan
5. Pelaporan dan ulasan
6. Stabilisasi

## Usulan stok opname otomatis

`php spark opname:suggest` menyusun usulan bahan yang perlu dihitung fisik (stok di bawah minimum, banyak terpakai oleh penjualan/retur sejak hitung terakhir, atau lebih dari 7 hari belum dihitung). Perintah ini hanya membuat usulan; sesi opname dan penguncian stok baru dibuat saat staf menekan "Mulai opname dari usulan terpilih" di halaman Stok opname.

Jadwal harian:

- Windows (localhost): `schtasks /Create /SC DAILY /ST 06:00 /TN "Marwis opname:suggest" /TR "cmd /c cd /d D:\Alternatif_D\laragon\www\marwis-sweet && php spark opname:suggest"`
- Hosting (cron): `0 6 * * * cd /path/ke/marwis-sweet && php spark opname:suggest >> writable/logs/opname-suggest.log 2>&1`

## Data contoh untuk evaluasi

`php spark db:seed EvaluationDataSeeder` (hanya development/testing) mengisi produk beserta gambar, banner, supplier, penerimaan stok, penjualan 15 hari terakhir lewat `SaleService`, shift, pengeluaran dengan berbagai status, dan ulasan. Gambar berupa ilustrasi bertanda "Gambar contoh" di `public/uploads/demo/`. Seeder menolak jalan bila ada sesi stok opname aktif, dan aman diulang. Data ditandai awalan `EVL-` pada SKU/nomor. Untuk mengosongkan semuanya: `php spark migrate:refresh --all` lalu `php spark db:seed DevelopmentSeeder`.
