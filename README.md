# Marwis Sweet — Sistem Informasi Martabak

Source code ini mengikuti urutan tahap pada [PRD](PRD%20(1).md). Status pekerjaan dicatat di [IMPLEMENTATION_PROGRESS.md](IMPLEMENTATION_PROGRESS.md).

## Menjalankan Tahap 1 — Fondasi

1. Pastikan PHP 8.2+, Composer 2.0.14+, Node.js, dan MySQL tersedia.
2. Buat database MySQL bernama `marwis_sweet` dengan charset `utf8mb4`.
3. Salin file `env` menjadi `.env`, lalu sesuaikan `app.baseURL`, kredensial MySQL, dan `BUSINESS_NAME`. Untuk HTTPS, gunakan URL `https://...` dan `cookie.secure = true`; HTTP lokal memakai `false`.
4. Pasang dependency: `composer install` dan `npm install`.
5. Bangun aset CSS lokal: `npm run build:css`.
6. Jalankan migrasi: `php spark migrate`.
7. Isi sementara `BOOTSTRAP_OWNER_NAME`, `BOOTSTRAP_OWNER_USERNAME`, `BOOTSTRAP_OWNER_EMAIL` (opsional), dan `BOOTSTRAP_OWNER_PASSWORD` di `.env`, lalu jalankan `php spark owner:create`. Command menghapus baris bootstrap setelah akun dibuat.
8. Jalankan server lokal dengan `php spark serve`.

Jangan menyimpan rahasia di file `env` yang dilacak Git. File `.env` sudah diabaikan Git. Untuk deployment web, arahkan document root ke folder `public`.

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
