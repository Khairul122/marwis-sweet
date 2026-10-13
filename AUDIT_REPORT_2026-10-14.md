# Laporan Audit Aplikasi Marwis Sweet

**Tanggal:** 14 Oktober 2026  
**Cakupan:** pemeriksaan kode dan tampilan terhadap `PRD (1).md`, `DESIGN.md`, dan `IMPLEMENTATION_PROGRESS.md`. Stack proyek: CodeIgniter 4, PHP, Tailwind CSS, MySQL.

## Ringkasan

**Status: belum siap untuk operasional penuh atau dinyatakan sesuai PRD.** Fitur inti sudah tersedia, tetapi ada kebocoran data biaya ke role kasir, jalur yang membuat pembukuan tidak cocok dengan kas/stok, laporan terfilter yang salah menjumlah, dan sejumlah kriteria POS/shift yang belum diterapkan.

Tahap 1–5 pada file progres ditandai selesai, namun beberapa butir baru terpenuhi sebagian. Tahap 6 (UAT, konkurensi, rollback, akses, HTTP/HTTPS, dan backup/restore) masih belum dikerjakan. Audit kode bersifat statis; tampilan POS juga diperiksa di browser pada lebar tablet sekitar 1022 px. Tidak ada test suite yang dijalankan, transaksi yang disubmit, atau data yang diubah.

## P1 — Bereskan sebelum pembukuan dan shift dipakai untuk operasional nyata

| ID | Temuan dan dampak | Bukti |
|---|---|---|
| A-01 | **PDF stok/opname membocorkan HPP dan nilai persediaan kepada kasir.** Route laporan hanya mensyaratkan login; service hanya membatasi laporan pengeluaran/keuangan. PDF stok menampilkan nilai mutasi, posisi stok, dan nilai selisih opname. Batasi nilai/route menurut role di server. | `Routes.php:105–109`; `OperationalReportService.php:15,24–35`; `operational_pdf.php:18–19` |
| A-02 | **Mutasi kas laci tidak masuk jurnal.** `cashMovement()` hanya menambah catatan `cash_movements`, sementara tutup shift menggunakannya menghitung kas seharusnya. Contoh: kas utama/laci tercatat Rp100.000; laci menyetor Rp40.000 ke kas utama; saldo kas laci buku tetap Rp100.000 walau hitungan shift Rp60.000. Posting mutasi dengan akun pasangan yang tepat. | `ShiftController.php:60–74,78–104` |
| A-03 | **Neraca menambah prive ke ekuitas.** Akun prive bersaldo normal debit, tetapi angka debitnya dibuat positif dan ditambahkan ke total ekuitas. Prive Rp100.000 menurunkan aset Rp100.000 tetapi laporan menaikkan ekuitas Rp100.000, sehingga neraca bisa meleset Rp200.000. Saldo akun kontra-ekuitas harus mengurangi ekuitas. | `AccountingController.php:52–59`; `CreateAccountingTables.php:36` |
| A-04 | **Saldo awal dapat diposting berulang.** ID sumber jurnal dibuat acak. Validasi nilai persediaan tidak mencegah posting saldo awal yang sama lagi; akun persediaan dapat menjadi Rp2.000.000 sementara buku stok tetap Rp1.000.000. Gunakan sumber yang stabil dan batasi saldo awal agar hanya dapat diposting sekali atau melalui koreksi terlacak. | `AccountingController.php:94–114`; `AccountingService.php` (kunci sumber jurnal) |
| A-05 | **Restock refund barang siap jual dapat mengembalikan biaya topping yang sudah dikonsumsi ke persediaan.** HPP sale item mencakup biaya bahan topping, lalu seluruh `unit_cogs` dipakai saat restock. Minuman biaya Rp2.000 dengan topping Rp1.000 yang direfund/re-stock menambah persediaan Rp3.000 walau topping tidak kembali. Pisahkan biaya barang yang direstock dari biaya bahan terpakai. | `SaleService.php:145–168`; `RefundService.php:162–210,226–229` |
| A-06 | **Filter laporan produk/kategori salah menghitung omzet dan HPP.** Transaksi berisi produk A Rp10.000 dan B Rp20.000, saat difilter A hanya menampilkan unit A tetapi omzet Rp30.000; refund B juga ikut dihitung. Hitung baris yang sesuai filter atau jelaskan dengan tegas bahwa total dihitung penuh per transaksi. | `SalesReportService.php:43–46,70–86` |
| A-07 | **Tutup shift dapat beradu dengan pembayaran.** Proses tutup membaca total kas dan mengubah status tanpa transaksi/`FOR UPDATE`, sementara finalisasi penjualan mengunci shift. Jika pembayaran selesai setelah pembacaan total, transaksi masuk ke shift yang sudah tertutup tetapi tidak tercakup dalam `expected_cash`. Kunci shift dan hitung ulang di dalam satu transaksi. Ini risiko dari urutan kode; belum diuji dengan request serentak. | `ShiftController.php:78–104`; `SaleService.php:31–35` |
| A-08 | **Retry pembayaran menonaktifkan token ulasan yang dipakai struk pertama.** Idempotensi mengembalikan transaksi yang sama, tetapi controller tetap membuat token baru dan menonaktifkan yang lama. Retry harus mempertahankan token aktif transaksi itu atau hanya menerbitkan token pada pembayaran baru. | `PosController.php:37–49`; `ReviewService.php:15–24` |
| A-09 | **Topping tersedia untuk semua produk, termasuk Air Mineral 600 ml.** POS memuat topping global dan service menerima topping tanpa memvalidasi kecocokan produk. Browser menunjukkan tombol topping pada air mineral; ini bisa mencatat tambahan harga/bahan yang tidak sesuai. Batasi pilihan di UI dan validasi server berdasarkan produk/varian. | `pos/index.php:44`; `PosController.php:22`; `SaleService.php:52–66` |
| A-10 | **Data seeder tampil ke publik seolah fakta usaha asli.** Beranda lokal memuat “Marwis Sweet Demo”, alamat contoh, nomor placeholder, dan ulasan seeder tanpa label contoh. Ini wajar untuk pengembangan karena data sementara memang diminta, tetapi harus ditandai sebagai demo atau dibersihkan sebelum situs dibuka untuk pelanggan. | `TemporaryDataSeeder.php:140–141`; `EvaluationDataSeeder.php:400–403`; `public/home.php:100,248–258` |

## P2 — Gap PRD, akurasi tambahan, dan pengalaman penggunaan

| ID | Temuan dan dampak | Bukti |
|---|---|---|
| B-01 | **AC-23 tutup shift belum lengkap:** selisih kas dan alasan disimpan, lalu shift langsung ditutup; tidak ada status atau tindakan tinjauan owner. | `ShiftController.php:95–105`; `CreateSalesTables.php:19–24` |
| B-02 | **Alur POS belum mencakup keranjang draft/tertahan, lanjutkan, pembatalan sebelum lunas, jenis pesanan, meja, nama pelanggan, dan catatan item.** Transaksi langsung berstatus `paid`. | `Routes.php:116–119`; `SaleService.php:139`; `CreateSalesTables.php:49–65` |
| B-03 | **Kebijakan diskon dan biaya tambahan belum diterapkan.** POS hanya menyediakan diskon nominal per transaksi; diskon item/persen dan batas persetujuan owner belum ada. Pajak dan biaya layanan selalu disimpan nol, jadi opsi konfigurasi PRD belum dapat digunakan. | `pos/index.php:62–63`; `SaleService.php:108–112,140` |
| B-04 | **Akses persediaan kasir dan penerimaan yang disetujui belum sesuai.** Modul stok sepenuhnya owner-only, sedangkan PRD memberi kasir akses stok dan penerimaan berdasarkan item/biaya yang disetujui. Skema penerimaan hanya draft/posted/cancelled. Belum ada alur saldo awal stok, kerusakan, koreksi stok luar opname, atau mutasi pembalik beralasan. | `Routes.php:64–70`; `CreateInventoryOperationTables.php:18,60` |
| B-05 | **Filter metode dapat menyembunyikan refund lintas metode.** Sale ID lebih dulu dibatasi menurut metode penjualan, kemudian refund juga difilter menurut metode pengembalian. Penjualan tunai yang refund-nya dibayar lewat bank tidak ditemukan saat filter bank. Filter penjualan dan refund perlu diterapkan secara terpisah. | `SalesReportService.php:61–67,148–158` |
| B-06 | **Pembulatan dapat membuat nilai baris tidak sama dengan header/jurnal.** Nilai uang dihitung sebagai `float`; HPP total dijurnal sebelum HPP tiap baris disimpan ke dua desimal. Tiga baris HPP Rp333,3333 dapat menghasilkan header/jurnal Rp1.000,00 tetapi total baris Rp999,99. Refund juga membulatkan per baris tanpa menaruh residu pada baris terakhir. Gunakan aritmetika presisi tetap dan alokasi sisa deterministik. | `SaleService.php:145–168`; `CreateSalesTables.php:85–86`; `RefundService.php:74–85,107–120` |
| B-07 | **Rata-rata bergerak dapat menyisakan nilai stok saat kuantitas nol.** Tiga unit berbiaya Rp1.000 menghasilkan rata-rata Rp333,3333; tiga penjualan terpisah mengurangi Rp333,33 per unit dan menyisakan Rp0,01 pada nilai persediaan. Alokasikan residu pada pelepasan terakhir. | `InventoryController.php:163–172`; `CreateMasterAndContentTables.php:34`; `CreateInventoryOperationTables.php:59`; `SaleService.php:145–176` |
| B-08 | **Akses shift terlalu luas.** Route login umum mengizinkan owner membuka POS/shift, padahal PRD meminta akun kasir terpisah. Halaman shift menampilkan riwayat semua kasir termasuk kas aktual/selisih; filterkan ke shift sendiri untuk kasir. | `Routes.php:110–119`; `ShiftController.php:21`; `shifts/index.php:24` |
| B-09 | **Katalog dan POS tidak menunjukkan ketersediaan/habis berdasarkan stok.** Produk tetap ditampilkan tanpa menghitung ketersediaan berbasis resep atau stok barang siap jual. | `Home.php:96–136`; `PosController.php:17–22` |
| B-10 | **Drawer POS dan sidebar mobile belum aksesibel dengan keyboard.** Saat drawer tertutup, kontrolnya tetap dalam urutan Tab dan pohon aksesibilitas; pada POS teramati 15 kontrol aktif. Fokus juga tidak dibatasi saat drawer terbuka. Terapkan `inert`/atribut aksesibilitas yang sesuai dan pengelolaan fokus. | `pos/index.php:53–54`; `app.js:20–41,357–379`; `layouts/panel.php:15–17` |
| B-11 | **POS belum mengikuti breakpoint tablet pada desain.** Keranjang baru menjadi kolom kedua pada breakpoint `xl`; pada viewport CSS sekitar 1022 px keranjang berupa drawer di luar area produk. Desain meminta dua area untuk tablet serta bottom sheet pada ponsel. | `pos/index.php:23,53–54`; `DESIGN.md:§6.1, §8.4, §9` |
| B-12 | **Tabel panel/laporan terlalu lebar untuk pola mobile di desain.** Tabel memakai lebar minimum dan gulir horizontal; belum ada baris blok berlabel atau header lengket. | `panel_helper.php:93–96`; `reports/index.php:39,41`; `DESIGN.md:§7` |
| B-13 | **Cetak ulang struk kehilangan tautan ulasan.** Token mentah hanya tersedia sekali lewat flashdata, sedangkan database menyimpan hash. Nama usaha struk membaca environment, bukan nilai pengaturan terbaru. Opsi PDF struk, QR, dan pilihan ukuran 58/80 mm belum ada. | `PosController.php:88`; `ReviewService.php:19–24`; `receipt.php:15–17,23,61,65`; `SettingsController.php:37` |
| B-14 | **Audit trail dan beberapa operasi master belum lengkap.** Tidak ditemukan tabel/layanan audit perubahan harga, resep, refund, stok, jurnal, akun, atau moderasi. Foto produk belum dapat diganti/diurutkan/dihapus; banner belum menyediakan pratinjau desktop/mobile atau penggantian gambar. | `Routes.php:28`; `CatalogController.php:273–307`; `SettingsController.php:99–185`; PRD §9–10 |
| B-15 | **Ekspor tahunan memuat seluruh hasil ke memori dan membuat PDF pada request yang sama.** Data penjualan/refund dan daftar ID filter tidak dibatasi atau dipaginasi. Risiko waktu/memori perlu diukur pada target 100.000 transaksi; PRD meminta ekspor besar diproses sebagai pekerjaan latar. | `SalesReportService.php:21–67,148–158`; `ReportController.php:23–60`; PRD §10 |
| B-16 | **Konfigurasi deployment belum siap untuk host dan keamanan HTTP/HTTPS.** `baseURL` hard-coded `http://localhost/`, HTTPS nonaktif, CSP nonaktif, dan filter secure headers dikomentari. URL ulasan/QR bisa menunjuk ke host lokal yang tidak dapat dijangkau pelanggan. Jadikan URL/flag konfigurasi deployment dan uji LAN serta HTTPS. | `Config/App.php:19,160,201`; `Config/Filters.php:83–85`; PRD §8 |
| B-17 | **Teks progres dashboard usang.** Dashboard masih menyebut ulasan sebagai tahap berikutnya dan Tahap 6 belum berjalan, padahal kode ulasan sudah ada. | `dashboard.php:16` |

## Cakupan dan kesiapan verifikasi

FR-01 sampai FR-11 mempunyai implementasi inti: login/role, katalog, master produk, penerimaan stok owner, POS, refund, opname, pengeluaran, jurnal, PDF, banner, dan ulasan. Namun seluruh area masih parsial; gap terbesar ada pada FR-04, FR-06, dan FR-09. AC-02 tidak terpenuhi karena PDF operasional kasir memuat biaya. AC-07 terganggu oleh rotasi token ulasan saat retry. AC-23 belum memiliki tinjauan owner. AC-08/09/16/21/24/25 dan target performa belum dapat dinyatakan lulus tanpa UAT, uji konkurensi/rollback, PDF multi-halaman, setup HTTP/HTTPS, dan pemulihan backup.

Folder `tests` berisi tes Health dan contoh bawaan CodeIgniter; belum terlihat tes untuk aturan POS, refund, persediaan, jurnal, role, atau laporan. Angka contoh di atas diturunkan dari jalur kode; risiko konkurensi dan performa perlu diuji di MySQL dengan dua kasir dan dataset representatif.

## Urutan tindak lanjut

1. Tutup kebocoran data biaya untuk kasir; batasi akses shift dan buat tutup shift atomik.
2. Benahi jurnal serta valuasi mutasi kas, prive, saldo awal, refund/restock, dan pembulatan HPP.
3. Perbaiki kalkulasi/filter laporan dan siapkan uji rekonsiliasi detail, total, stok, serta jurnal.
4. Lengkapi aturan POS: retry token, topping yang kompatibel, maksimal dua metode pembayaran di server, diskon/pajak/layanan, serta alur keranjang PRD.
5. Selesaikan alur stok, tinjauan selisih shift, audit trail, dan pembagian hak owner/kasir.
6. Rapikan drawer/tabel mobile, struk/cetak ulang, kemudian selesaikan Tahap 6 termasuk target 100.000 transaksi, concurrency, HTTP/HTTPS, backup/restore, dan panduan.

## Catatan workspace

Saat audit terdapat perubahan lokal pada `app/Views/layouts/panel.php`, `public/assets/css/app.css`, dan `resources/css/app.css` dari permintaan tampilan sebelumnya. Tidak ada file aplikasi yang diubah selama audit ini; file report ini satu-satunya artefak baru.
