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

## BUG-E2E-004

Fallback konfirmasi native sekarang membiarkan submit asli berlanjut bila diterima, tanpa `requestSubmit()` bersarang. Layout POS memakai dialog konfirmasi yang sama dengan panel sebagai anak langsung body, di luar cart yang inert. Fokus awal ada pada tombol Batal. Screenshot `writable/e2e/bug004-dialog.png` diperiksa: judul, isi, dan tombol utuh.

POS-08 lulus untuk dialog HTML serta fallback native (tolak lalu terima), tanpa perubahan stok/jurnal. Seluruh spec POS: 11 lulus, satu expected failure BUG-E2E-003, satu skip POS-12 yang ditangani pada GAP-01. Assertion lama dipertahankan; hanya langkah konfirmasi disesuaikan. Kolom alasan pembatalan tidak ditambahkan karena membutuhkan keputusan owner sesuai plan.

## BUG-E2E-001

Filter login menyimpan path dan query hanya untuk GET dokumen; POST, AJAX, dan PDF tidak menimpa tujuan. Setelah login berhasil, tujuan diambil sebelum regenerasi sesi, dihapus, lalu divalidasi dengan whitelist path panel. Filter role tetap berjalan pada tujuan tersebut.

PHPUnit: delapan kasus lulus, 89 assertion. Spec autentikasi: 17 lulus, termasuk tujuan laporan, query, login langsung, URL eksternal/protocol-relative, dan tujuan owner yang tetap menolak kasir. Fixture sesi eksternal hanya tersedia melalui router E2E dengan guard schema, nama sesi E2E, dan CSRF aktif; route aplikasi tidak ditambah.

## BUG-E2E-003

Helper `payment_method_label()` menyamakan label Tunai, QRIS, Transfer bank, Kartu debit, dan Kartu kredit pada struk, refund, serta laporan HTML/PDF. Struk non-tunai menampilkan penanda konfirmasi manual dan nomor referensi. Laporan memberi keterangan konfirmasi manual bila memuat penerimaan non-tunai; ini diperlukan oleh assertion POS-02 yang sudah ada pada halaman laporan.

POS-02 lulus dengan assertion lama serta pemeriksaan tambahan pada HTML, PDF, dan nomor referensi. Struk desktop, mobile 390 px, serta PDF satu halaman dirender dan diperiksa secara visual; label, referensi, dan nominal utuh. Tidak ada overflow horizontal pada mobile. Artefak di `writable/e2e/bug003-*`.

Seluruh spec POS lulus: 12 tes lulus, satu skip POS-12 yang dilengkapi pada GAP-01, tanpa expected failure yang tersisa di spec ini.

## BUG-E2E-005

Enam bahan demo baru dimulai dari saldo nol lalu mendapatkan mutasi `opening` dan jurnal Debit Persediaan / Kredit Modal pemilik melalui `AccountingService`, total Rp2.373.000. Bahan yang sudah ada dilewati untuk pengisian saldo, sehingga stok terpakai serta data legacy tidak direset atau dikoreksi otomatis.

Tiga tes regresi semula gagal dan kini lulus (94 assertion): posting awal, pengulangan setelah stok/biaya berubah, serta perlindungan stok legacy. Lima tes spec akuntansi lulus, termasuk ACC-06 dengan selisih persediaan Rp0. Lima penanda bug awal telah dihapus tanpa mengubah assertion aslinya. Petunjuk reset database development dan penyesuaian data lama ada di README E2E; seeder tidak dijalankan pada database lokal berisi riwayat.
