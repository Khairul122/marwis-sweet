# Hasil perbaikan E2E

Mengikuti [E2E-FIX-PLAN.md](E2E-FIX-PLAN.md), dimulai 19 Oktober 2026 dari BUG-E2E-002.

## BUG-E2E-002

- Backup lokal: `writable/backup-before-fk-fix.sql`, 654.432 byte, SHA-256 `fd7f6e7519b481445e958d131dd6a69d1739b33cf7355e834f97cf11eb9efe5d`.
- Backup berhasil direstore ke `marwis_sweet_fk_backup_verify`; fingerprint isi seluruh 41 tabel cocok sebelum migration. Dump dan bukti rinci tidak masuk Git.
- Migration `000024` memperbaiki 55 FK: 53 menjadi DELETE RESTRICT / UPDATE CASCADE, dua menjadi DELETE SET NULL / UPDATE CASCADE. Sebanyak 23 constraint yang sudah benar dipertahankan.
- Nama constraint hasil perbaikan memakai akhiran `_fk24`. MySQL 8.4.3 menolak DROP + ADD dengan nama sama dalam satu ALTER (error 1826), termasuk dengan INPLACE. Ini sesuai [MySQL bug #113226](https://bugs.mysql.com/bug.php?id=113226). Nama baru menjaga satu ALTER atomik per tabel dengan `foreign_key_checks` tetap aktif; `down()` memulihkan nama dan aturan lama hanya pada 55 constraint tersebut.
- Uji `up`, `up` ulang, `down`, `down` ulang, lalu `up` pada salinan backup lulus; 78 constraint tetap ada dan checks tetap 1.
- Controller menolak penghapusan produk dengan transaksi atau ulasan. Penghapusan bahan tanpa riwayat membersihkan konversi/usulan opname dalam transaksi. Supplier yang masih terpakai mendapat pesan kesalahan. Penghapusan anak milik produk/topping, foto, kategori, dan banner diperiksa; cascade yang memang diperlukan tetap dipertahankan.
- Regression PHPUnit semula gagal pada dua kasus: aturan FK terbalik dan produk terjual dapat dihapus. Setelah perbaikan, `composer test -- --no-coverage` melalui runner terisolasi lulus: 25 tes, 265 assertion. Cleanup fixture pembalik kini menghapus anak sebelum induk.
- E2E penuh setelah BUG-002: 96 tes, 89 lulus, empat bug lain masih expected failure, tiga dilewati, nol kegagalan tak terduga; durasi 931,9 detik. Assertion CAT-04 tetap dan penanda `test.fail()` dihapus.

Migration sudah diterapkan ke `marwis_sweet_e2e` dan `marwis_sweet` setelah backup terverifikasi. Query metadata menunjukkan nol aturan terbalik pada database lokal. Fingerprint 40 tabel data tetap cocok dengan backup (termasuk 156 penjualan dan 326 item penjualan); hanya tabel `migrations` bertambah satu baris. Bukti ada di `writable/e2e/local-fk-migration.json`.
