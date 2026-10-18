# Hasil E2E Marwis Sweet

Run utama: 18/10/2026, 20.54.57 WIB. Schema: `marwis_sweet_e2e`. Browser: Chromium; mobile 390 x 844. Workers: 1, CSRF aktif, tanpa perubahan kode aplikasi.

## Ringkasan

| Hasil run utama | Jumlah |
| --- | --- |
| Tes terdaftar | 96 |
| Lulus sesuai perilaku yang diharapkan | 88 |
| Gagal karena bug yang sudah direproduksi (test.fail) | 5 |
| Kegagalan tak terduga | 0 |
| Dilewati | 3 |

Durasi run utama: 693.3 detik. Evaluasi PDF terpisah: 1 lulus, 29.5 detik. PHPUnit: OK (22 tests, 234 assertions). TypeScript diperiksa dengan `npm run test:e2e:types`.

Pemeriksaan akhir database: 0 jurnal tidak seimbang; selisih akun persediaan tepat Rp2.373.000, seluruhnya sesuai stok demo tanpa jurnal awal. Lampiran runtime dari 161 sesi halaman mencatat 0 error JavaScript. Delapan tes terpilih diulang dengan trace aktif: tiga lulus dan lima bug yang sama tereproduksi, tanpa kegagalan baru. Pengulangan tersebut juga memeriksa HTML mentah penolakan kasir dan keseimbangan jurnal setelah pembayaran mobile.

Playwright memasukkan kegagalan `test.fail()` ke hasil yang diharapkan. Lima skenario tersebut tetap berstatus Gagal dalam laporan ini. Hasil ini belum memenuhi seluruh kriteria rilis.

## Status acceptance criteria

| AC | Spec/test | Status | Catatan / bug |
| --- | --- | --- | --- |
| AC-01 | 01 AUTH-06; 02 PUB-01/05; 14 BAN-01 | Lulus | Katalog/banner dapat diakses tamu; panel, POS, dan PDF panel menolak tamu. |
| AC-02 | 01 AUTH-07/08/09 | Lulus | Grup owner dan POST dengan CSRF sah menolak kasir; PDF kasir tanpa HPP/laba. |
| AC-03 | 03 CAT-03 | Lulus | Harga, resep, dan HPP transaksi lama tetap. CAT-04 menemukan bug penghapusan terpisah. |
| AC-04 | 05 POS-01 | Lulus | Rp50.000 dikurangi diskon Rp5.000; total Rp45.000, kembalian Rp5.000. |
| AC-05 | 05 POS-02; 12 RPT-01/05 | Gagal | Satu penjualan dan dua alokasi benar; label dikonfirmasi manual tidak tersedia (BUG-E2E-003). |
| AC-06 | 05 POS-03 | Lulus | Bahan berkurang 200 g; satu jurnal gabungan penjualan/HPP, tanpa duplikasi baris HPP. |
| AC-07 | 05 POS-04 | Lulus | Klik ganda dan replay idempotency key menunjuk penjualan sama. |
| AC-08 | 06 CON-01 | Lulus | Dua browser context, dua kasir/shift, dua proses PHP; satu berhasil dan stok akhir nol. |
| AC-09 | 05 POS-12; PHPUnit | Belum diuji | Fault injection kegagalan jurnal saat finalize tidak tersedia lewat UI. Suite PHPUnit yang ada tidak secara khusus membuktikan kasus ini. |
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
| AC-24 | 12 RPT-03; RPT-04/06 evaluasi terpisah | Lulus | Periode kosong valid; 158 transaksi menghasilkan 6 halaman, header berulang dan nomor halaman. |
| AC-25 | Manual restore UAT | Belum diuji | Prosedur backup/restore deployment beserta RPO/RTO belum diuji. Pemulihan insiden lokal didokumentasikan terpisah. |

## Temuan yang tidak diperbaiki

| ID | Skenario | Prioritas / dampak | Hasil aktual |
| --- | --- | --- | --- |
| BUG-E2E-001 | AUTH-12 / FR-01 | P1 | Login dari /panel/reports selalu menuju /panel; tujuan awal hilang. |
| BUG-E2E-002 | CAT-04 / FR-03 | Kritis | Produk yang telah terjual dapat dihapus. ON DELETE CASCADE menghapus sale_items sementara baris sales tetap ada. |
| BUG-E2E-003 | POS-02 / AC-05 | P0 pada plan | Pembayaran gabungan benar secara nominal, tetapi UI tidak menampilkan label dikonfirmasi manual. |
| BUG-E2E-004 | POS-08 / FR-06 | P1 | Konfirmasi native pembatalan pesanan held diterima, tetapi POST tidak terkirim dan status tetap held. |
| BUG-E2E-005 | ACC-06 / FR-08 | Data awal | DevelopmentSeeder mengisi persediaan demo Rp2.373.000 tanpa jurnal saldo awal; rekonsiliasi total tidak cocok meskipun semua jurnal posted seimbang. |

Assertion asli tetap dipertahankan dan setiap temuan memiliki `test.fail()` beserta komentar ID. BUG-E2E-002 memengaruhi riwayat transaksi dan perlu ditangani sebelum rilis.

## Penyesuaian terhadap plan dan batas cakupan

- Server memakai development karena mode testing tidak mempertahankan sesi HTTP. Bootstrap dan router khusus E2E menjaga override Windows; cache/sesi E2E terpisah. Pemeriksaan CLI dan HTTP dilakukan sebelum migrasi.
- Penolakan role mengikuti aplikasi: GET redirect 302, POST redirect 303. Tidak diasumsikan sebagai 403.
- Penjualan dan HPP berada dalam satu jurnal gabungan yang seimbang. Tes memeriksa bahwa masing-masing baris pendapatan dan HPP tidak berganda.
- Syarat finansial AC-05 dalam PRD terpenuhi. Status Gagal pada tabel mengikuti tambahan label konfirmasi manual dalam panduan E2E.
- Form jurnal manual hanya memiliki satu nominal untuk kedua sisi. Nominal debit/kredit berbeda tidak dapat dimasukkan melalui UI; tes negatif memakai akun debit/kredit sama. Pengujian jurnal arbitrer tidak seimbang belum dibuktikan oleh E2E.
- Kuantitas refund berlebih, nominal pengeluaran nol, komentar pendek, batas panjang komentar, pilihan rating 1 sampai 5, dan batas lima foto diperiksa melalui batas form. Bypass payload untuk semua batas tersebut tidak diklaim sudah diuji.
- Publik menyediakan pencarian dan kategori. Filter ketersediaan tersendiri tidak tersedia pada UI. Kartu stok memperlihatkan waktu/jenis/jumlah/referensi; pelaksana diverifikasi melalui database.
- Pembatalan held tidak menyediakan isian alasan. Pagination transaksi diuji dengan fixture evaluasi; pagination refund melalui browser belum dicakup.
- P2 yang belum dicakup: urutan foto, usulan opname, kategori pengeluaran nonaktif, konsistensi dashboard, penghapusan EXIF GPS dengan fixture ber-GPS, dan pengukuran tap target.

## Bukti dan pengulangan

- [Panduan menjalankan suite](../e2e/README.md)
- [Laporan HTML terakhir](../playwright-report/index.html), JSON lengkap di `test-results/results.json`.
- Trace dan screenshot kegagalan ada di `test-results/`. Lampiran runtime mencatat error JavaScript, warning/error console, dan respons HTTP gagal, termasuk 403/404 yang sengaja diuji.
- Trace dan screenshot lima temuan yang sudah diketahui tersimpan di `writable/e2e/verification-tests/`. Playwright tidak mempertahankan trace `retain-on-failure` untuk kegagalan yang sudah ditandai `test.fail()`; pengulangan khusus memakai `--trace on`.
- PDF evaluasi: `writable/e2e/evaluation-sales.pdf`; jumlah halaman dan header: `writable/e2e/evaluation-pdf.json`; hasil run terpisah: `writable/e2e/evaluation-results.json`.
- Keenam halaman PDF dirender dan diperiksa secara visual; tabel, nominal, header berulang, serta footer terbaca. PNG ada di `writable/e2e/pdf-review/`. Tidak ada blok teks di luar batas halaman.
- Log PHPUnit: `writable/e2e/phpunit.log`.
- [Insiden persiapan dan pemulihan database lokal](E2E-INCIDENT-2026-10-18.md). Database lokal dipulihkan ke keadaan sebelum kejadian; fingerprint 41 tabel diverifikasi terhadap rekonstruksi binary log.

## Daftar tes run utama

| Project | Tes | Status | Durasi |
| --- | --- | --- | --- |
| chromium | AUTH-01 FR-01 owner masuk melalui username dan email | Lulus | 3.4 detik |
| chromium | AUTH-02 FR-01 kasir memiliki navigasi sesuai role | Lulus | 1.2 detik |
| chromium | AUTH-06 AC-01 tamu ditolak pada panel, POS, dan PDF | Lulus | 1.3 detik |
| chromium | AUTH-03 FR-01 password salah tidak mengungkap keberadaan akun | Lulus | 1.8 detik |
| chromium | AUTH-04 FR-01 percobaan keenam dibatasi meskipun password benar | Lulus | 5.2 detik |
| chromium | AUTH-05 FR-01 logout, Back, dan akses ulang panel | Lulus | 2.3 detik |
| chromium | AUTH-07 AC-02 seluruh grup owner menolak URL langsung kasir | Lulus | 5.7 detik |
| chromium | AUTH-08 AC-02 POST dengan CSRF sah tetap ditolak untuk role kasir | Lulus | 4.0 detik |
| chromium | AUTH-09 AC-02 PDF kasir tanpa HPP, laba, atau buku besar | Lulus | 2.2 detik |
| chromium | AUTH-10 FR-01 POS dan shift khusus kasir menolak owner | Lulus | 2.3 detik |
| chromium | AUTH-11 FR-01 akun dinonaktifkan mengakhiri sesi kasir aktif | Lulus | 4.9 detik |
| chromium | AUTH-12 FR-01 login mengembalikan tamu ke laporan yang dituju | Gagal | 9.1 detik |
| chromium | PUB-01/05 FR-02 informasi publik dan harga tanpa stok angka atau biaya internal | Lulus | 2.5 detik |
| chromium | PUB-02 FR-02 detail memuat foto, varian, harga, dan status ulasan | Lulus | 4.7 detik |
| chromium | PUB-03 FR-02 produk nonaktif hilang dari beranda, detail, dan POS | Lulus | 9.6 detik |
| chromium | PUB-04 FR-02 pencarian dan kategori menyaring katalog | Lulus | 6.4 detik |
| chromium | PUB-06 FR-02 simpan identitas menonaktifkan penanda demo | Lulus | 4.7 detik |
| chromium | CAT-01 FR-03 owner membuat kategori, produk, resep, varian, dan topping | Lulus | 11.1 detik |
| chromium | CAT-05 FR-03 maksimum lima foto produk pada UI | Lulus | 8.3 detik |
| chromium | CAT-02 FR-03 konversi satu kilogram menjadi 1000 gram | Lulus | 3.1 detik |
| chromium | CAT-03 AC-03 harga dan HPP lama tetap setelah perubahan harga dan resep | Lulus | 16.6 detik |
| chromium | CAT-04 FR-03 produk terpakai transaksi tidak dapat dihapus | Gagal | 6.7 detik |
| chromium | INV-01/02 AC-15 penerimaan 1 kg Rp12000 menambah persediaan tanpa beban | Lulus | 8.4 detik |
| chromium | INV-06 FR-04 kartu stok memuat jenis, waktu, jumlah, dan referensi mutasi | Lulus | 9.1 detik |
| chromium | INV-07 FR-04 penyesuaian stok negatif ditolak sebelum persetujuan | Lulus | 12.8 detik |
| chromium | INV-03 FR-04 kasir mengajukan penerimaan, owner approve, kasir posting | Lulus | 8.1 detik |
| chromium | INV-04 FR-04 penerimaan ditolak tidak menambah stok | Lulus | 7.4 detik |
| chromium | INV-05 FR-04 penyesuaian disetujui dan pembalik mempertahankan mutasi asal | Lulus | 15.0 detik |
| chromium | POS-01 AC-04 diskon Rp5000, tunai Rp50000, kembalian Rp5000 | Lulus | 5.9 detik |
| chromium | POS-02 AC-05 tunai Rp20000 dan QRIS Rp25000 dihitung satu transaksi | Gagal | 14.5 detik |
| chromium | POS-03 AC-06 dua porsi memakai 200 gram dan jurnal HPP sekali | Lulus | 12.9 detik |
| chromium | POS-04 AC-07 klik bayar dua kali dan replay kunci idempotensi tidak menggandakan | Lulus | 5.5 detik |
| chromium | POS-05 AC-11 opname mengunci stok saat keranjang dibayar | Lulus | 15.0 detik |
| chromium | POS-06 FR-06 diskon di atas batas memerlukan kredensial owner | Lulus | 5.9 detik |
| chromium | POS-07 FR-06 keranjang ditahan, dilanjutkan, lalu dibayar | Lulus | 6.8 detik |
| chromium | POS-08 FR-06 pembatalan keranjang held tidak mengubah stok atau jurnal | Gagal | 14.3 detik |
| chromium | POS-10 FR-06 pembayaran kurang ditolak | Lulus | 5.7 detik |
| chromium | POS-09 FR-06 topping, catatan tanpa kacang, dan meja tercetak pada struk | Lulus | 15.5 detik |
| chromium | POS-11 FR-06 struk HTML dan PDF memuat transaksi serta tautan ulasan | Lulus | 6.0 detik |
| chromium | POS-12 AC-09 rollback saat jurnal gagal dibuktikan melalui PHPUnit | Belum diuji | 0.0 detik |
| chromium | CON-01 AC-08 dua kasir memperebutkan satu stok lewat dua proses PHP | Lulus | 17.6 detik |
| chromium | CON-02 FR-06 kasir tidak dapat membuka shift kedua | Lulus | 2.2 detik |
| chromium | REF-01/05 AC-12 refund penuh produk resep membalik uang tanpa mengembalikan tepung | Lulus | 15.2 detik |
| chromium | REF-02 AC-13 barang layak jual kembali dengan biaya transaksi asal | Lulus | 14.4 detik |
| chromium | REF-03 AC-12 refund kumulatif melebihi pembelian ditolak di form | Lulus | 14.2 detik |
| chromium | REF-04/06 FR-11 refund ditolak tanpa jurnal, kemudian refund sebagian | Lulus | 17.7 detik |
| chromium | OPN-01/06 AC-10 stok 10 fisik 8 diposting sekali dan tidak dapat diedit | Lulus | 15.4 detik |
| chromium | OPN-04 FR-05 bukti opname privat untuk owner dan ditolak bagi tamu | Lulus | 7.6 detik |
| chromium | OPN-02 FR-05 owner meminta koreksi dan kasir mengajukan ulang | Lulus | 17.8 detik |
| chromium | OPN-03 FR-05 pembatalan membuka kunci dan POS dapat menjual lagi | Lulus | 15.2 detik |
| chromium | EXP-01/02 AC-14 approval tidak membukukan, pembayaran membukukan sekali | Lulus | 6.4 detik |
| chromium | EXP-04 FR-07 bukti pengeluaran lebih dari 5 MB ditolak | Lulus | 3.7 detik |
| chromium | EXP-03 FR-07 pembayaran dari laci mencatat shift aktif | Lulus | 6.5 detik |
| chromium | EXP-04 FR-07 nominal nol ditolak form | Lulus | 1.8 detik |
| chromium | ACC-01 FR-08 jurnal manual menolak akun debit dan kredit yang sama | Lulus | 4.1 detik |
| chromium | ACC-02 FR-08 jurnal seimbang dengan alasan dan audit | Lulus | 4.2 detik |
| chromium | ACC-03/04 AC-22 periode tertutup menolak posting dan dapat dibuka dengan alasan | Lulus | 8.2 detik |
| chromium | ACC-05 FR-08 saldo awal selalu seimbang | Lulus | 4.0 detik |
| chromium | ACC-06 FR-08 rekonsiliasi seluruh jurnal dan nilai akun persediaan | Gagal | 2.4 detik |
| chromium | SHF-01/02/03/04 AC-23 modal, transaksi, kas keluar, tutup shift, dan review owner | Lulus | 10.7 detik |
| chromium | SHF-03 AC-23 penolakan review shift tidak membuat jurnal selisih | Lulus | 6.3 detik |
| chromium | RPT-01/05 AC-16 total panel dan PDF harian, bulanan, tahunan serta filter pembayaran gabungan | Lulus | 10.7 detik |
| chromium | RPT-02 AC-17 refund hari ini dari penjualan bulan lalu muncul beserta transaksi asal | Lulus | 9.7 detik |
| chromium | RPT-03 AC-24 periode kosong menampilkan total nol dan PDF valid | Lulus | 2.4 detik |
| chromium | RPT-07 FR-09 seluruh PDF operasional menerapkan izin owner dan kasir | Lulus | 9.2 detik |
| chromium | RPT-04/06 AC-24 PDF multi-halaman dan pagination dengan EvaluationDataSeeder | Belum diuji | 1.2 detik |
| chromium | REV-01/02/08 AC-18 ulasan tamu pending, disetujui, lalu disembunyikan | Lulus | 12.1 detik |
| chromium | REV-03 AC-19 pengiriman ulang untuk item sama ditolak | Lulus | 7.8 detik |
| chromium | REV-04 AC-19 token kedaluwarsa tidak membuka data transaksi | Lulus | 6.2 detik |
| chromium | REV-05 AC-19 foto tidak sah ditolak kasus 1 | Lulus | 7.7 detik |
| chromium | REV-05 AC-19 foto tidak sah ditolak kasus 2 | Lulus | 7.3 detik |
| chromium | REV-05 AC-19 foto tidak sah ditolak kasus 3 | Lulus | 8.6 detik |
| chromium | REV-06 FR-11 komentar pendek ditolak, rating hanya 1 sampai 5 | Lulus | 6.9 detik |
| chromium | REV-07 FR-11 token acak tidak membocorkan transaksi | Lulus | 0.4 detik |
| chromium | BAN-01 FR-10 banner aktif tampil dengan alt text | Lulus | 3.9 detik |
| chromium | BAN-02 AC-20 banner nonaktif, lewat jadwal, dan belum waktunya tidak tampil | Lulus | 8.0 detik |
| chromium | BAN-03 FR-10 tautan javascript ditolak | Lulus | 2.4 detik |
| chromium | BAN-05 FR-10 carousel dapat dioperasikan dengan keyboard | Lulus | 5.7 detik |
| chromium | BAN-04 FR-10 hero tetap tersedia ketika seluruh banner dinonaktifkan | Lulus | 8.7 detik |
| chromium | USR-01 FR-01 owner membuat kasir, mereset password, dan menonaktifkan | Lulus | 7.3 detik |
| chromium | USR-02 FR-01 username dan email duplikat ditolak | Lulus | 2.7 detik |
| chromium | AUD-01 FR-08 audit tampil dan tidak menyimpan password atau token mentah | Lulus | 2.9 detik |
| chromium | SEC-01 PRD-10 POST tanpa CSRF ditolak tanpa perubahan data | Lulus | 1.8 detik |
| chromium | SEC-02 PRD-10 bukti pengeluaran kasir lain tidak dapat dibuka | Lulus | 7.0 detik |
| chromium | SEC-03 PRD-10 upload product tidak sah ditolak kasus 1 | Lulus | 3.7 detik |
| chromium | SEC-03 PRD-10 upload product tidak sah ditolak kasus 2 | Lulus | 3.5 detik |
| chromium | SEC-03 PRD-10 upload product tidak sah ditolak kasus 3 | Lulus | 4.9 detik |
| chromium | SEC-03 PRD-10 upload banner tidak sah ditolak kasus 1 | Lulus | 2.3 detik |
| chromium | SEC-03 PRD-10 upload banner tidak sah ditolak kasus 2 | Lulus | 2.3 detik |
| chromium | SEC-03 PRD-10 upload banner tidak sah ditolak kasus 3 | Lulus | 3.4 detik |
| chromium | SEC-04 PRD-10 nama berkas gambar disimpan acak | Lulus | 3.8 detik |
| chromium | SEC-05 PRD-10 nama produk berisi script di-escape pada halaman publik | Lulus | 4.3 detik |
| chromium | SEC-06 PRD-10 header keamanan tersedia | Lulus | 0.4 detik |
| chromium | SEC-07 AC-21 HTTPS perlu domain dan sertifikat staging | Belum diuji | 0.0 detik |
| mobile | MOB-01 FR-02 @mobile publik, detail, dan form ulasan tanpa scroll horizontal pada 390px | Lulus | 10.9 detik |
| mobile | MOB-02 FR-06 @mobile kasir menambah produk dan membayar pada 390px | Lulus | 6.2 detik |
