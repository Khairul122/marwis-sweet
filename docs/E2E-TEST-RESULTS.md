# Hasil E2E Marwis Sweet

Run verifikasi setelah perbaikan: 19/10/2026, 01.42.23 WIB. Schema: `marwis_sweet_e2e`. Browser: Chromium; mobile 390 x 844. Workers: 1, CSRF aktif, toolbar development nonaktif khusus server E2E. Mengikuti [E2E-FIX-PLAN.md](E2E-FIX-PLAN.md).

## Ringkasan

| Hasil run utama | Jumlah |
| --- | --- |
| Tes terdaftar | 110 |
| Lulus sesuai perilaku yang diharapkan | 108 |
| Gagal karena bug yang sudah direproduksi (test.fail) | 0 |
| Kegagalan tak terduga | 0 |
| Dilewati | 2 |

Durasi run utama: 673.4 detik. Evaluasi PDF terpisah: 1 lulus, 44.5 detik. PHPUnit: OK (40 tests, 491 assertions). TypeScript diperiksa dengan `npm run test:e2e:types`.

Pemeriksaan akhir schema E2E: 0 jurnal tidak seimbang, selisih persediaan Rp0, 0 FK terbalik. Lampiran 190 sesi halaman mencatat 0 error JavaScript, 2 pesan console warning/error, dan 2 respons HTTP gagal. Respons negatif yang disengaja dicatat pada JSON ringkasan.

Kelima penanda `test.fail()` awal dihapus; assertion asli dipertahankan. Kolom kegagalan diketahui tetap memisahkan bug aplikasi dari status expected Playwright. AC-21 dan AC-25 masih memerlukan UAT staging sesuai DEPLOYMENT-UAT.md.

## Status acceptance criteria

| AC | Spec/test | Status | Catatan / bug |
| --- | --- | --- | --- |
| AC-01 | 01 AUTH-06; 02 PUB-01/05; 14 BAN-01 | Lulus | Katalog/banner dapat diakses tamu; panel, POS, dan PDF panel menolak tamu. |
| AC-02 | 01 AUTH-07/08/09 | Lulus | Grup owner dan POST dengan CSRF sah menolak kasir; PDF kasir tanpa HPP/laba. |
| AC-03 | 03 CAT-03/04; ForeignKeyDeleteRulesTest | Lulus | Snapshot harga/resep/HPP tetap; penghapusan produk terjual ditolak controller dan FK. |
| AC-04 | 05 POS-01 | Lulus | Rp50.000 dikurangi diskon Rp5.000; total Rp45.000, kembalian Rp5.000. |
| AC-05 | 05 POS-02; 12 RPT-01/05 | Lulus | Satu penjualan dan dua alokasi benar; label konfirmasi manual dan referensi ada pada HTML/PDF/laporan. |
| AC-06 | 05 POS-03 | Lulus | Bahan berkurang 200 g; satu jurnal gabungan penjualan/HPP, tanpa duplikasi baris HPP. |
| AC-07 | 05 POS-04 | Lulus | Klik ganda dan replay idempotency key menunjuk penjualan sama. |
| AC-08 | 06 CON-01 | Lulus | Dua browser context, dua kasir/shift, dua proses PHP; satu berhasil dan stok akhir nol. |
| AC-09 | 05 POS-12; SaleServiceRollbackTest | Lulus | Periode berjalan ditutup melalui UI; jurnal ditolak dan seluruh penjualan, pembayaran, mutasi, jurnal, serta stok rollback. PHPUnit menguji transaksi top-level yang sama. |
| AC-10 | 08 OPN-01/06 | Lulus | Stok 10 menjadi 8; approve ulang tidak menggandakan mutasi/jurnal. |
| AC-11 | 05 POS-05 | Lulus | Opname yang dimulai setelah keranjang dibuat menolak pembayaran. |
| AC-12 | 07 REF-01/05 | Lulus | Refund membalik dana; bahan resep tidak kembali. |
| AC-13 | 07 REF-02 | Lulus | Barang siap jual kembali dan HPP dibalik memakai biaya transaksi asal. |
| AC-14 | 09 EXP-01/02 | Lulus | Approve belum mengurangi kas; pay membuat satu jurnal dan mengurangi kas Rp10.000. |
| AC-15 | 04 INV-01/02 | Lulus | Penerimaan 1 kg bernilai Rp12.000 menambah persediaan tanpa beban operasional. |
| AC-16 | 12 RPT-01/05 | Lulus | Total panel dan PDF harian/bulanan/tahunan cocok; pembayaran gabungan tidak dihitung ganda. |
| AC-17 | 12 RPT-02 | Lulus | Refund hari ini dari penjualan bulan lalu muncul dengan nomor asal. |
| AC-18 | 13 REV-01/02/08 | Lulus | Tamu unggah JPEG; pending, terbit setelah approve, rata-rata/jumlah ulasan berubah. |
| AC-19 | 13 REV-03/04/05 | Lulus | Duplikasi, token kedaluwarsa, JPG palsu, SVG, dan foto besar ditolak. |
| AC-20 | 14 BAN-02 | Lulus | Banner nonaktif, lewat jadwal, dan belum waktunya tidak tampil. |
| AC-21 | 16 SEC-07 | Belum diuji | HTTPS, sertifikat, Secure cookie, dan proxy perlu staging. |
| AC-22 | 10 ACC-03/04 | Lulus | Periode tertutup menolak posting, lalu dapat dibuka dengan alasan. |
| AC-23 | 11 SHF-01/02/03/04 | Lulus | Kas seharusnya Rp235.000; fisik Rp230.000; jurnal hanya setelah approve. Penolakan review juga diuji. |
| AC-24 | 12 RPT-03; RPT-04/06 evaluasi terpisah | Lulus | Periode kosong valid; 148 transaksi, 51 refund, 7 halaman PDF. Pagination transaksi/refund menjaga total; header dan nomor halaman berulang. |
| AC-25 | Manual restore UAT | Belum diuji | Prosedur backup/restore deployment beserta RPO/RTO belum diuji. Pemulihan insiden lokal didokumentasikan terpisah. |

## Status temuan

| ID | Skenario | Prioritas | Status | Diperbaiki di commit | Perilaku setelah perbaikan |
| --- | --- | --- | --- | --- | --- |
| BUG-E2E-001 | AUTH-12..16 / FR-01 | P1 | Diperbaiki | 8e3546a | Tujuan lokal dan query dipulihkan; tujuan eksternal ditolak dan role tetap diperiksa. |
| BUG-E2E-002 | CAT-04 / FR-03 | Kritis | Diperbaiki | 0c04d14 | 55 FK diperbaiki dan produk dengan riwayat ditolak saat dihapus; backup dibuat sebelum migration lokal. |
| BUG-E2E-003 | POS-02 / AC-05 | P0 pada plan E2E | Diperbaiki | 43b5d4d | Label metode, konfirmasi manual, dan referensi konsisten pada struk serta laporan. |
| BUG-E2E-004 | POS-08 / FR-06 | P1 | Diperbaiki | 9ce90de | Dialog POS dan fallback native mengirim pembatalan; penolakan konfirmasi mempertahankan pesanan. |
| BUG-E2E-005 | ACC-06 / FR-08 | P2 data demo | Diperbaiki | f3521bc | Bahan demo baru memiliki saldo awal dan jurnal modal; rerun mempertahankan stok terpakai dan legacy. |
| BUG-E2E-006 | Setup evaluasi / GAP-04 | P2 seeder evaluasi | Diperbaiki | 1254083 | EvaluationDataSeeder menyertakan ID owner ketika menolak pengeluaran; setup evaluasi tidak lagi ArgumentCountError. |

Urutan commit mengikuti plan: 002, 004, 001, 003, 005. [Bukti per tahap, backup, dan migration lokal](E2E-FIX-RESULTS.md). Data development lama tidak dikoreksi otomatis oleh seeder.

## Penyesuaian terhadap plan dan batas cakupan

- Server memakai development karena mode testing tidak mempertahankan sesi HTTP. Bootstrap dan router khusus E2E menjaga override Windows; cache/sesi E2E terpisah. Pemeriksaan CLI dan HTTP dilakukan sebelum migrasi.
- CI_DEBUG=false hanya pada server HTTP E2E menghindari race file history toolbar saat dua proses PHP aktif. Runtime observer memeriksa kedua kasir dan menggagalkan seluruh HTTP 5xx. Bukti run sebelum isolasi toolbar dipertahankan pada laporan perbaikan.
- Penolakan role mengikuti aplikasi: GET redirect 302, POST redirect 303. Tidak diasumsikan sebagai 403.
- Penjualan dan HPP berada dalam satu jurnal gabungan yang seimbang. Tes memeriksa bahwa masing-masing baris pendapatan dan HPP tidak berganda.
- GAP-01: POS-12 menutup periode berjalan lewat UI untuk menggagalkan posting, memeriksa rollback enam tabel dan stok, lalu membuka periode kembali dalam finally. PHPUnit menguji SaleService dalam transaksi top-level.
- GAP-02: form jurnal tetap menguji akun debit/kredit sama. AccountingServiceValidationTest menolak nominal tidak seimbang, debit+kredit pada satu baris, dan baris nol tanpa posting.
- GAP-03: SRV-01..08 mengirim request cookie+CSRF sah yang melewati batas HTML: refund melebihi sisa setelah pengajuan pertama, pengeluaran nol, komentar 9/1001 karakter, rating 0/6, foto produk keenam, dan empat foto ulasan. Assertion memeriksa pesan domain dan 13 tabel tetap.
- Publik menyediakan pencarian dan kategori. Filter ketersediaan tersendiri tidak tersedia pada UI. Kartu stok memperlihatkan waktu/jenis/jumlah/referensi; pelaksana diverifikasi melalui database.
- GAP-04: fixture E2E membuat 51 refund melalui RefundService setelah EvaluationDataSeeder. Browser memeriksa perpindahan halaman refund dengan total tetap.
- Pembatalan held belum menyediakan isian alasan. Plan meminta keputusan owner tersendiri untuk fitur tersebut; kolom tidak ditambah pada sesi ini.
- GAP-05 opsional belum dicakup: urutan foto, usulan opname, kategori pengeluaran nonaktif, konsistensi dashboard, penghapusan EXIF GPS dengan fixture ber-GPS, dan pengukuran tap target.
- GAP-06 tetap manual di staging: HTTPS serta restore/RPO/RTO. Backup dan restore verifikasi lokal pada sesi ini tidak menggantikan UAT tersebut.

## Bukti dan pengulangan

- [Panduan menjalankan suite](../e2e/README.md)
- [Laporan HTML terakhir](../playwright-report/index.html), JSON lengkap di `test-results/results.json`.
- Trace dan screenshot kegagalan ada di `test-results/`. Lampiran runtime mencatat error JavaScript, warning/error console, dan respons HTTP gagal, termasuk 403/404 yang sengaja diuji.
- Bukti tahap BUG-002 ada di `writable/e2e/bug002-results.json`. Screenshot dialog POS serta struk desktop/mobile/PDF ada di `writable/e2e/bug003-*` dan `writable/e2e/bug004-dialog.png`.
- PDF evaluasi: `writable/e2e/evaluation-sales.pdf`; jumlah halaman dan header: `writable/e2e/evaluation-pdf.json`; hasil run terpisah: `writable/e2e/evaluation-results.json`.
- PDF evaluasi terbaru dirender untuk pemeriksaan tabel, nominal, header, dan footer. Hasil visual ada di `writable/e2e/pdf-review/`; detail halaman dicatat pada laporan perbaikan.
- Log PHPUnit: `writable/e2e/phpunit.log`.
- Backup sebelum migration FK: `writable/backup-before-fk-fix.sql`, SHA-256 `fd7f6e7519b481445e958d131dd6a69d1739b33cf7355e834f97cf11eb9efe5d`. Restore verifikasi 41 tabel cocok; setelah migration, 40 tabel data tetap, hanya catatan migrations bertambah. Bukti privat: `writable/e2e/backup-before-fk-fix.json` dan `writable/e2e/local-fk-migration.json`.
- [Catatan insiden sesi pengujian sebelumnya](E2E-INCIDENT-2026-10-18.md) dipertahankan sebagai riwayat. Sesi perbaikan ini memakai backup baru sebelum migration.

## Daftar tes run utama

| Project | Tes | Status | Durasi |
| --- | --- | --- | --- |
| chromium | AUTH-01 FR-01 owner masuk melalui username dan email | Lulus | 3.4 detik |
| chromium | AUTH-02 FR-01 kasir memiliki navigasi sesuai role | Lulus | 1.3 detik |
| chromium | AUTH-06 AC-01 tamu ditolak pada panel, POS, dan PDF | Lulus | 1.2 detik |
| chromium | AUTH-03 FR-01 password salah tidak mengungkap keberadaan akun | Lulus | 1.8 detik |
| chromium | AUTH-04 FR-01 percobaan keenam dibatasi meskipun password benar | Lulus | 4.4 detik |
| chromium | AUTH-05 FR-01 logout, Back, dan akses ulang panel | Lulus | 2.2 detik |
| chromium | AUTH-07 AC-02 seluruh grup owner menolak URL langsung kasir | Lulus | 5.4 detik |
| chromium | AUTH-08 AC-02 POST dengan CSRF sah tetap ditolak untuk role kasir | Lulus | 3.4 detik |
| chromium | AUTH-09 AC-02 PDF kasir tanpa HPP, laba, atau buku besar | Lulus | 7.9 detik |
| chromium | AUTH-10 FR-01 POS dan shift khusus kasir menolak owner | Lulus | 2.0 detik |
| chromium | AUTH-11 FR-01 akun dinonaktifkan mengakhiri sesi kasir aktif | Lulus | 4.5 detik |
| chromium | AUTH-12 FR-01 login mengembalikan tamu ke laporan yang dituju | Lulus | 1.2 detik |
| chromium | AUTH-13 login langsung menuju dashboard | Lulus | 1.1 detik |
| chromium | AUTH-14 tujuan eksternal https://evil.invalid tidak menjadi redirect login | Lulus | 1.4 detik |
| chromium | AUTH-14 tujuan eksternal //evil.invalid tidak menjadi redirect login | Lulus | 1.3 detik |
| chromium | AUTH-15 query tujuan dipertahankan setelah login | Lulus | 1.2 detik |
| chromium | AUTH-16 tujuan owner tetap menolak kasir setelah login | Lulus | 1.5 detik |
| chromium | PUB-01/05 FR-02 informasi publik dan harga tanpa stok angka atau biaya internal | Lulus | 2.5 detik |
| chromium | PUB-02 FR-02 detail memuat foto, varian, harga, dan status ulasan | Lulus | 4.4 detik |
| chromium | PUB-03 FR-02 produk nonaktif hilang dari beranda, detail, dan POS | Lulus | 7.4 detik |
| chromium | PUB-04 FR-02 pencarian dan kategori menyaring katalog | Lulus | 6.0 detik |
| chromium | PUB-06 FR-02 simpan identitas menonaktifkan penanda demo | Lulus | 4.0 detik |
| chromium | CAT-01 FR-03 owner membuat kategori, produk, resep, varian, dan topping | Lulus | 9.9 detik |
| chromium | CAT-05 FR-03 maksimum lima foto produk pada UI | Lulus | 7.7 detik |
| chromium | CAT-02 FR-03 konversi satu kilogram menjadi 1000 gram | Lulus | 3.0 detik |
| chromium | CAT-03 AC-03 harga dan HPP lama tetap setelah perubahan harga dan resep | Lulus | 14.4 detik |
| chromium | CAT-04 FR-03 produk terpakai transaksi tidak dapat dihapus | Lulus | 5.9 detik |
| chromium | INV-01/02 AC-15 penerimaan 1 kg Rp12000 menambah persediaan tanpa beban | Lulus | 7.4 detik |
| chromium | INV-06 FR-04 kartu stok memuat jenis, waktu, jumlah, dan referensi mutasi | Lulus | 8.4 detik |
| chromium | INV-07 FR-04 penyesuaian stok negatif ditolak sebelum persetujuan | Lulus | 11.3 detik |
| chromium | INV-03 FR-04 kasir mengajukan penerimaan, owner approve, kasir posting | Lulus | 7.2 detik |
| chromium | INV-04 FR-04 penerimaan ditolak tidak menambah stok | Lulus | 6.4 detik |
| chromium | INV-05 FR-04 penyesuaian disetujui dan pembalik mempertahankan mutasi asal | Lulus | 12.9 detik |
| chromium | POS-01 AC-04 diskon Rp5000, tunai Rp50000, kembalian Rp5000 | Lulus | 5.0 detik |
| chromium | POS-02 AC-05 tunai Rp20000 dan QRIS Rp25000 dihitung satu transaksi | Lulus | 6.1 detik |
| chromium | POS-03 AC-06 dua porsi memakai 200 gram dan jurnal HPP sekali | Lulus | 11.3 detik |
| chromium | POS-04 AC-07 klik bayar dua kali dan replay kunci idempotensi tidak menggandakan | Lulus | 4.9 detik |
| chromium | POS-05 AC-11 opname mengunci stok saat keranjang dibayar | Lulus | 13.4 detik |
| chromium | POS-06 FR-06 diskon di atas batas memerlukan kredensial owner | Lulus | 4.8 detik |
| chromium | POS-07 FR-06 keranjang ditahan, dilanjutkan, lalu dibayar | Lulus | 5.8 detik |
| chromium | POS-08 FR-06 pembatalan keranjang held tidak mengubah stok atau jurnal | Lulus | 5.7 detik |
| chromium | POS-08 fallback konfirmasi native menolak lalu menerima pembatalan held | Lulus | 5.4 detik |
| chromium | POS-10 FR-06 pembayaran kurang ditolak | Lulus | 4.6 detik |
| chromium | POS-09 FR-06 topping, catatan tanpa kacang, dan meja tercetak pada struk | Lulus | 13.4 detik |
| chromium | POS-11 FR-06 struk HTML dan PDF memuat transaksi serta tautan ulasan | Lulus | 5.0 detik |
| chromium | POS-12 AC-09 periode tertutup menggagalkan jurnal dan rollback seluruh finalisasi | Lulus | 13.1 detik |
| chromium | CON-01 AC-08 dua kasir memperebutkan satu stok lewat dua proses PHP | Lulus | 14.2 detik |
| chromium | CON-02 FR-06 kasir tidak dapat membuka shift kedua | Lulus | 1.8 detik |
| chromium | REF-01/05 AC-12 refund penuh produk resep membalik uang tanpa mengembalikan tepung | Lulus | 13.3 detik |
| chromium | REF-02 AC-13 barang layak jual kembali dengan biaya transaksi asal | Lulus | 12.2 detik |
| chromium | REF-03 AC-12 refund kumulatif melebihi pembelian ditolak di form | Lulus | 12.7 detik |
| chromium | REF-04/06 FR-11 refund ditolak tanpa jurnal, kemudian refund sebagian | Lulus | 15.3 detik |
| chromium | OPN-01/06 AC-10 stok 10 fisik 8 diposting sekali dan tidak dapat diedit | Lulus | 12.8 detik |
| chromium | OPN-04 FR-05 bukti opname privat untuk owner dan ditolak bagi tamu | Lulus | 6.5 detik |
| chromium | OPN-02 FR-05 owner meminta koreksi dan kasir mengajukan ulang | Lulus | 14.8 detik |
| chromium | OPN-03 FR-05 pembatalan membuka kunci dan POS dapat menjual lagi | Lulus | 12.4 detik |
| chromium | EXP-01/02 AC-14 approval tidak membukukan, pembayaran membukukan sekali | Lulus | 5.5 detik |
| chromium | EXP-04 FR-07 bukti pengeluaran lebih dari 5 MB ditolak | Lulus | 3.1 detik |
| chromium | EXP-03 FR-07 pembayaran dari laci mencatat shift aktif | Lulus | 5.7 detik |
| chromium | EXP-04 FR-07 nominal nol ditolak form | Lulus | 1.6 detik |
| chromium | ACC-01 FR-08 jurnal manual menolak akun debit dan kredit yang sama | Lulus | 2.5 detik |
| chromium | ACC-02 FR-08 jurnal seimbang dengan alasan dan audit | Lulus | 2.4 detik |
| chromium | ACC-03/04 AC-22 periode tertutup menolak posting dan dapat dibuka dengan alasan | Lulus | 5.3 detik |
| chromium | ACC-05 FR-08 saldo awal selalu seimbang | Lulus | 2.4 detik |
| chromium | ACC-06 FR-08 rekonsiliasi seluruh jurnal dan nilai akun persediaan | Lulus | 1.4 detik |
| chromium | SHF-01/02/03/04 AC-23 modal, transaksi, kas keluar, tutup shift, dan review owner | Lulus | 8.0 detik |
| chromium | SHF-03 AC-23 penolakan review shift tidak membuat jurnal selisih | Lulus | 5.1 detik |
| chromium | RPT-01/05 AC-16 total panel dan PDF harian, bulanan, tahunan serta filter pembayaran gabungan | Lulus | 9.2 detik |
| chromium | RPT-02 AC-17 refund hari ini dari penjualan bulan lalu muncul beserta transaksi asal | Lulus | 9.9 detik |
| chromium | RPT-03 AC-24 periode kosong menampilkan total nol dan PDF valid | Lulus | 2.4 detik |
| chromium | RPT-07 FR-09 seluruh PDF operasional menerapkan izin owner dan kasir | Lulus | 10.1 detik |
| chromium | RPT-04/06 AC-24 PDF multi-halaman dan pagination dengan EvaluationDataSeeder | Belum diuji | 1.3 detik |
| chromium | REV-01/02/08 AC-18 ulasan tamu pending, disetujui, lalu disembunyikan | Lulus | 11.0 detik |
| chromium | REV-03 AC-19 pengiriman ulang untuk item sama ditolak | Lulus | 7.0 detik |
| chromium | REV-04 AC-19 token kedaluwarsa tidak membuka data transaksi | Lulus | 5.4 detik |
| chromium | REV-05 AC-19 foto tidak sah ditolak kasus 1 | Lulus | 6.1 detik |
| chromium | REV-05 AC-19 foto tidak sah ditolak kasus 2 | Lulus | 6.1 detik |
| chromium | REV-05 AC-19 foto tidak sah ditolak kasus 3 | Lulus | 7.5 detik |
| chromium | REV-06 FR-11 komentar pendek ditolak, rating hanya 1 sampai 5 | Lulus | 5.9 detik |
| chromium | REV-07 FR-11 token acak tidak membocorkan transaksi | Lulus | 0.4 detik |
| chromium | BAN-01 FR-10 banner aktif tampil dengan alt text | Lulus | 3.1 detik |
| chromium | BAN-02 AC-20 banner nonaktif, lewat jadwal, dan belum waktunya tidak tampil | Lulus | 6.2 detik |
| chromium | BAN-03 FR-10 tautan javascript ditolak | Lulus | 2.1 detik |
| chromium | BAN-05 FR-10 carousel dapat dioperasikan dengan keyboard | Lulus | 4.3 detik |
| chromium | BAN-04 FR-10 hero tetap tersedia ketika seluruh banner dinonaktifkan | Lulus | 7.3 detik |
| chromium | USR-01 FR-01 owner membuat kasir, mereset password, dan menonaktifkan | Lulus | 6.2 detik |
| chromium | USR-02 FR-01 username dan email duplikat ditolak | Lulus | 2.5 detik |
| chromium | AUD-01 FR-08 audit tampil dan tidak menyimpan password atau token mentah | Lulus | 2.3 detik |
| chromium | SEC-01 PRD-10 POST tanpa CSRF ditolak tanpa perubahan data | Lulus | 1.5 detik |
| chromium | SEC-02 PRD-10 bukti pengeluaran kasir lain tidak dapat dibuka | Lulus | 6.0 detik |
| chromium | SEC-03 PRD-10 upload product tidak sah ditolak kasus 1 | Lulus | 3.2 detik |
| chromium | SEC-03 PRD-10 upload product tidak sah ditolak kasus 2 | Lulus | 3.2 detik |
| chromium | SEC-03 PRD-10 upload product tidak sah ditolak kasus 3 | Lulus | 4.7 detik |
| chromium | SEC-03 PRD-10 upload banner tidak sah ditolak kasus 1 | Lulus | 2.1 detik |
| chromium | SEC-03 PRD-10 upload banner tidak sah ditolak kasus 2 | Lulus | 2.1 detik |
| chromium | SEC-03 PRD-10 upload banner tidak sah ditolak kasus 3 | Lulus | 3.3 detik |
| chromium | SEC-04 PRD-10 nama berkas gambar disimpan acak | Lulus | 3.2 detik |
| chromium | SEC-05 PRD-10 nama produk berisi script di-escape pada halaman publik | Lulus | 3.0 detik |
| chromium | SEC-06 PRD-10 header keamanan tersedia | Lulus | 0.4 detik |
| chromium | SEC-07 AC-21 HTTPS perlu domain dan sertifikat staging | Belum diuji | 0.0 detik |
| chromium | SRV-01 GAP-03 server menolak refund melebihi sisa unit setelah refund pertama | Lulus | 11.9 detik |
| chromium | SRV-02 GAP-03 server menolak pengeluaran nominal nol meskipun batas HTML dilewati | Lulus | 1.8 detik |
| chromium | SRV-03 GAP-03 server menolak komentar 9 karakter | Lulus | 5.6 detik |
| chromium | SRV-04 GAP-03 server menolak komentar 1001 karakter | Lulus | 5.5 detik |
| chromium | SRV-05 GAP-03 server menolak rating 0 | Lulus | 5.6 detik |
| chromium | SRV-06 GAP-03 server menolak rating 6 | Lulus | 5.9 detik |
| chromium | SRV-07 GAP-03 server menolak unggahan foto produk keenam | Lulus | 7.8 detik |
| chromium | SRV-08 GAP-03 server menolak empat foto pada satu ulasan | Lulus | 5.7 detik |
| mobile | MOB-01 FR-02 @mobile publik, detail, dan form ulasan tanpa scroll horizontal pada 390px | Lulus | 8.5 detik |
| mobile | MOB-02 FR-06 @mobile kasir menambah produk dan membayar pada 390px | Lulus | 4.6 detik |
