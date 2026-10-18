# Hasil perbaikan E2E

Mengikuti [E2E-FIX-PLAN.md](E2E-FIX-PLAN.md), dimulai 19 Oktober 2026 dari BUG-E2E-002.

Hasil akhir: **108 E2E lulus, nol gagal, dua skip**, evaluasi PDF terpisah lulus, dan **40 PHPUnit/491 assertion lulus**. Lima bug awal serta bug seeder evaluasi tambahan diperbaiki pada branch `fix/e2e-regressions`. [Laporan lengkap dan status 25 AC](E2E-TEST-RESULTS.md).

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

## BUG-E2E-006 tambahan: setup evaluasi gagal

Run evaluasi pertama berhenti dengan `ArgumentCountError` karena `EvaluationDataSeeder` memanggil `ExpenseService::reject()` dengan dua argumen, sedangkan service membutuhkan ID owner sebagai argumen ketiga. Pemanggilan diperbarui mengikuti pola approve/pay seeder yang sama. Ini temuan pada fixture evaluasi, bukan kelolosan payload GAP-03.

Setelah perbaikan, setup evaluasi lengkap dan RPT-04/06 lulus, termasuk dua halaman refund. Tidak ada perbaikan tambahan pada service pengeluaran. Hasil tersimpan di `writable/e2e/evaluation-results.json`.

## Celah cakupan

- GAP-01: POS-12 lulus melalui UI dengan periode berjalan tertutup. Enam tabel finansial dan snapshot stok tetap; key idempotensi tidak tersimpan. Periode dibuka kembali dalam finally. SaleServiceRollbackTest lulus dengan transaksi top-level.
- GAP-02: AccountingServiceValidationTest lulus untuk jurnal tidak seimbang, debit+kredit bersamaan, serta baris nol. Empat tes PHPUnit GAP-01/02 lulus, 43 assertion.
- GAP-03: delapan tes request langsung lulus dengan cookie/CSRF sah, pesan domain, serta pemeriksaan 13 tabel. Batas refund, nominal, komentar, rating, dan foto ditolak server. Locator form ulasan dipersempit setelah percobaan awal ikut mencocokkan toolbar development; ini kesalahan tes yang dikoreksi, bukan bug aplikasi baru.
- GAP-04: fixture khusus schema E2E membuat 51 refund melalui RefundService. RPT-04/06 lulus: halaman pertama berisi 50 refund, halaman kedua satu refund, total tidak berubah. Data evaluasi menghasilkan 148 transaksi dan tujuh halaman PDF. Sebanyak 24 transaksi contoh yang membutuhkan otorisasi diskon owner dilewati oleh EvaluationDataSeeder; jumlah aktual yang dipakai assertion tetap berasal dari database.
- Seluruh tujuh halaman PDF dirender dan diperiksa secara visual: tabel transaksi/refund, header berulang, nominal, dan nomor halaman utuh. Pemeriksaan bounding box tidak menemukan teks di luar halaman. Artefak `writable/e2e/pdf-review/fix-page-1.png` sampai `fix-page-7.png` dan `writable/e2e/pdf-visual-review.json`.
- GAP-05 bersifat opsional dan belum ditambahkan: urutan foto, usulan opname, kategori pengeluaran nonaktif, konsistensi dashboard, fixture EXIF GPS, dan audit tap target menyeluruh.
- GAP-06 tetap dilakukan manual di staging sesuai plan: AC-21 HTTPS dan AC-25 restore/RPO/RTO.

PHPUnit penuh setelah seluruh perubahan: **40 tes, 491 assertion, lulus**, melalui runner yang memanggil `composer test -- --no-coverage` hanya pada schema E2E.

## Perbaikan runner setelah audit lampiran

Run penuh pertama setelah semua perbaikan menghasilkan 108 lulus/dua skip, tetapi lampiran runtime mencatat satu HTTP 500 pada CON-01. Log pukul 01:30:45 WIB menunjukkan race `History::setFiles()` framework: dua proses PHP menghapus file debugbar yang sama, lalu `unlink()` pada proses kedua gagal. Ini request toolbar development, bukan request checkout; satu transaksi dan saldo stok yang benar tetap dibuktikan oleh tes. Bukti run tersebut disimpan terpisah di `writable/e2e/full-with-toolbar-results.json`.

Bootstrap server HTTP E2E kini menetapkan `CI_DEBUG=false`; mode development untuk sesi dan filter CSRF dipertahankan. Source aplikasi maupun framework tidak diubah untuk kasus toolbar. Runtime observer juga menolak seluruh HTTP 5xx dan mengamati browser kasir kedua. Suite penuh diulang dengan profil ini agar hasil akhir membuktikan transaksi sekaligus runtime tanpa 5xx.

## Verifikasi akhir

- Run utama terakhir: 110 tes, 108 lulus, dua skip, nol expected failure, nol kegagalan tak terduga, tanpa retry; 673,4 detik. Dua skip adalah evaluasi PDF yang dijalankan terpisah dan HTTPS staging.
- Lampiran 190 sesi halaman: nol JavaScript error dan nol HTTP 5xx. Dua respons 404 yang tersisa memang berasal dari pengujian produk nonaktif dan bukti pengeluaran kasir lain; keduanya juga menghasilkan pesan console resource gagal yang diharapkan.
- Evaluasi diulang dengan profil runner yang sama: satu lulus, 44,5 detik; 148 transaksi, 51 refund, tujuh halaman PDF. Semua halaman hasil terakhir kembali dirender dan diperiksa secara visual.
- Snapshot utama: nol jurnal tidak seimbang, nilai persediaan dan buku besar sama-sama Rp2.710.680, nol FK terbalik, 34 jenis audit event. Snapshot tersimpan sebelum reset evaluasi, sehingga tetap mewakili run utama.
- TypeScript, lint PHP yang diubah, pemeriksaan sintaks reporter, dan whitespace diff lulus. PHPUnit tetap dijalankan dengan konfigurasi CLI testing yang terisolasi; perubahan CI_DEBUG hanya berlaku pada server HTTP E2E.
- Pemeriksaan lokal setelah suite mencocokkan seluruh 41 tabel dan 78 FK dengan snapshot setelah migration: tidak ada perubahan. Bukti `writable/e2e/local-post-suite-verification.json`. Dibanding backup awal, hanya satu baris catatan migration yang bertambah; 40 tabel data tetap utuh.
- Status acceptance criteria: 23 lulus; AC-21 dan AC-25 masih manual staging. GAP-05 opsional tetap tercatat sebagai belum dicakup.

## Pemeriksaan antislop pada perubahan UI

Cakupan pemeriksaan ini adalah dialog konfirmasi POS dan label pembayaran. Acuan `DESIGN.md` dibaca sebelum perubahan: POS memakai ENERGY 2 / RHYTHM 3 / MOTION 1; panel memakai 1 / 2 / 1. Audit UI seluruh produk pada GAP-05 tidak diklaim selesai.

| Butir | Hasil | Bukti |
| --- | --- | --- |
| R-02, teks | PASS | Teks baru dalam Bahasa Indonesia; label metode singkat dan tidak memakai em dash. |
| R-03, responsif | PASS | Struk 390 px diperiksa secara visual dan dengan scrollWidth; nominal/referensi utuh tanpa overflow horizontal. |
| R-17, angka | PASS | Nominal, jumlah transaksi, dan referensi berasal dari transaksi fixture yang benar-benar diposting. |
| R-18, testimoni | PASS | Tidak ada testimoni atau profil orang baru pada UI. |
| R-23, aset | PASS | Tidak membuat aset visual produk; dialog memakai komponen panel yang ada. |
| R-24, navigasi | PASS | Tombol Batal/Lanjutkan dan link struk/PDF memakai tujuan aplikasi yang diuji. |
| R-25, teks/warna | PASS | Pemeriksa kontras: cocoa/putih 16,44:1, muted/putih 7,64:1, cocoa/kuning 10,74:1. Palet tidak diubah. |
| R-26, tindakan | PASS | Konfirmasi HTML serta native, tolak/terima, dan POST pembatalan dibuktikan dengan status database. |
| R-27, keadaan | PASS | Penolakan pembayaran, jurnal, dan validasi form tetap menampilkan pesan; konfirmasi batal mempertahankan pesanan. |
| R-28, FAQ | PASS | Tidak menambahkan FAQ atau konten pengisi. |
| R-32, keyboard | PASS | POS-08 memeriksa fokus awal Batal, Escape, Tab ke Lanjutkan, lalu Enter untuk submit. |
| R-33, implementasi | PASS | Perbaikan ditulis langsung di source view dan JS aplikasi; tidak ada script yang menulis ulang source saat runtime. |
| R-34, tema | PASS | Komponen memakai palet yang sudah ditetapkan; tidak menambah tema atau toggle. |
| R-35, bukti runtime | PASS | Spec autentikasi/POS dijalankan di Chromium; struk HTML/mobile/PDF dan dialog diperiksa. |
| R-36, klaim | PASS | Tidak menambah klaim pemasaran, keamanan, atau performa. |
| R-37, arah | PASS | Mempertahankan Warung Poster dan komponen yang ada dalam DESIGN.md. |
| R-38, data | PASS | Data demo tetap berlabel demo; perubahan UI hanya menampilkan keadaan transaksi yang ada. |
| Purpose-Gate | PASS | Tidak menambah gradient, ikon, grid, ilustrasi, atau animasi; dialog dipakai untuk konfirmasi tindakan dan label untuk kejelasan pembayaran. |
| Liveliness | PASS | Hierarki struk, angka utama, warna kuning tindakan, dan pemisah struk dipertahankan sesuai dials produk. |
| C-1..C-5 dan konsistensi | PASS | Perubahan menyelesaikan tindakan konkret, memakai komponen yang sama, diuji pada berhasil/gagal, dan didukung bukti runtime serta snapshot database. |
