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

## Urutan pengerjaan

1. Fondasi
2. Master dan publik
3. Operasional
4. Kontrol keuangan
5. Pelaporan dan ulasan
6. Stabilisasi
