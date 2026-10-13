# Checklist Perbaikan Marwis Sweet

Urutan mengikuti risiko dan ketergantungan. Temuan dirujuk dengan ID di `AUDIT_REPORT_2026-10-14.md`.

## Fase 1 — Akses dan integritas shift

### Tugas 1 — Lindungi nilai HPP dan nilai persediaan pada laporan kasir (A-01)

**P1 · Ketergantungan:** tidak ada.

- [x] Batasi laporan stok/opname bernilai uang untuk owner, atau buat hasil kasir tanpa `total_cost`, valuasi, HPP, dan nilai selisih.
- [x] Terapkan pembatasan pada service dan pilih template PDF berbasis role, bukan sekadar menyembunyikan tautan.

**Status:** Implementasi selesai dan sintaks PHP valid. Uji PDF langsung dengan sesi owner/kasir masih perlu dilakukan.

**Terima bila:** akun kasir menerima penolakan atau PDF tanpa angka biaya; owner tetap bisa mencetak PDF lengkap; AC-02 berhasil.

**Verifikasi nanti:** uji route PDF langsung dengan sesi owner dan kasir, termasuk request tanpa menu UI.

### Tugas 2 — Batasi halaman shift menurut role dan pemilik shift (B-08)

**P1 · Ketergantungan:** Tugas 1.

- [x] Kasir hanya melihat shift miliknya dan ringkasan kas miliknya.
- [x] POS/buka shift hanya untuk kasir; owner memakai akun kasir terpisah sesuai PRD.

**Status:** Implementasi selesai; akses langsung melalui sesi owner/kasir masih perlu diuji.

**Terima bila:** URL langsung tidak membocorkan kasir lain; route owner tidak bisa memulai transaksi POS dengan role owner.

**Verifikasi nanti:** akses langsung per role dan pemeriksaan isi halaman shift.

### Tugas 3 — Jadikan penutupan shift atomik (A-07)

**P1 · Ketergantungan:** Tugas 2.

- [x] Dalam satu transaksi database, kunci baris shift, hitung ulang pembayaran/refund/pengeluaran/mutasi, simpan kas seharusnya, kas aktual, selisih, dan status tutup.
- [x] Finalisasi penjualan yang kalah memperoleh pesan jelas bahwa shift sudah ditutup.

**Status:** Implementasi selesai; sintaks dan whitespace diff valid. Uji konkurensi MySQL masih perlu dilakukan.

**Terima bila:** pembayaran yang bertarung dengan tutup shift hanya tercatat seluruhnya sebelum tutup atau ditolak; tidak ada penjualan pada shift tertutup yang terlewat di `expected_cash`.

**Verifikasi nanti:** tes integrasi dua request MySQL yang berjalan serentak.

### Tugas 4 — Tambahkan review owner untuk selisih shift (B-01)

**P1 · Ketergantungan:** Tugas 3.

- [x] Simpan status review, owner peninjau, waktu, keputusan, dan alasan.
- [x] Selisih tidak membuat jurnal penyesuaian sebelum owner menyetujui.

**Status:** Implementasi selesai. Migrasi menandai selisih lama sebagai pending; persetujuan mem-posting jurnal idempoten dalam transaksi yang sama. Sintaks PHP valid. Alur UI dan database MySQL belum diverifikasi langsung.

**Terima bila:** shift tanpa selisih dapat ditutup; shift berselisih tercatat menunggu review; hanya owner dapat menyetujui/menolak; keputusan tidak dapat diposting dua kali.

**Verifikasi nanti:** alur kasir tutup shift dan owner review, termasuk URL langsung oleh kasir.

## Fase 2 — Rekonsiliasi kas, stok, dan buku besar

### Tugas 5 — Posting jurnal untuk mutasi kas laci (A-02)

**P1 · Ketergantungan:** keputusan akun pasangan pada `tasks/plan.md`.

- [ ] Tambahkan sumber/tujuan akun jika `in/out` tidak selalu berarti transfer kas utama ↔ laci.
- [ ] Catat jurnal berpasangan dalam transaksi yang sama dengan `cash_movements`; gunakan sumber unik agar retry tidak menggandakan jurnal.

**Terima bila:** setiap jenis kas masuk/keluar memiliki pasangan akun yang terdokumentasi; saldo akun kas utama dan laci berubah sesuai mutasi shift.

**Verifikasi nanti:** cocokkan saldo sebelum/sesudah dengan jurnal dan tutup shift.

**Menunggu aturan bisnis:** `in/out` saat ini tidak menyebut sumber atau tujuan. Perlu ditetapkan apakah selalu transfer kas utama ↔ laci atau mutasi dapat berasal dari/setor ke akun lain; jurnal belum dibuat agar saldo buku besar tidak salah.

### Tugas 6 — Koreksi tanda saldo prive pada neraca (A-03)

**P1 · Ketergantungan:** tidak ada.

- [x] Sajikan akun prive sebagai pengurang ekuitas meskipun saldo normal akun adalah debit.
- [x] Pertahankan tanda saldo normal yang benar untuk akun aset, kewajiban, dan modal.

**Status:** Implementasi selesai; sintaks PHP valid. Perhitungan tetap perlu direkonsiliasi dengan data MySQL nyata.

**Terima bila:** setelah jurnal prive Rp100.000, aset dan ekuitas sama-sama turun Rp100.000 dan persamaan neraca tetap seimbang.

**Verifikasi nanti:** uji perhitungan neraca untuk modal, prive, dan laba berjalan.

### Tugas 7 — Cegah saldo awal diposting berulang (A-04)

**P1 · Ketergantungan:** tidak ada.

- [x] Gunakan kunci sumber stabil/unique untuk saldo awal, bukan ID acak baru setiap pengiriman.
- [x] Tolak posting kedua atau arahkan ke jurnal koreksi eksplisit dengan alasan.

**Status:** Implementasi selesai; akun aset menjadi kunci sumber dan jurnal saldo awal lama pada akun tersebut juga dideteksi. Sintaks PHP valid. Pengecekan request ganda secara langsung belum diverifikasi.

**Terima bila:** mengirim ulang saldo awal tidak mengubah saldo; jalur koreksi tetap tercatat dan seimbang.

**Verifikasi nanti:** kirim request identik dua kali dan bandingkan satu jurnal, akun persediaan, serta nilai kartu stok.

### Tugas 8 — Pisahkan biaya barang dan biaya topping pada refund restock (A-05)

**P1 · Ketergantungan:** definisi snapshot biaya pada item penjualan.

- [x] Simpan biaya barang siap jual terpisah dari biaya bahan topping.
- [x] Saat restock, balik hanya biaya barang yang benar-benar kembali; jangan mengembalikan topping konsumsi ke persediaan.

**Status:** Implementasi selesai. Biaya per unit barang langsung tersimpan terpisah, dan migrasi mengisi data lama dari kartu stok. Tahap 9 menyelesaikan pembulatan refund bertahap dan sisa HPP restock.

**Terima bila:** refund minuman Rp2.000 + topping Rp1.000 yang hanya mengembalikan minuman menambah stok dan membalik HPP Rp2.000 saja.

**Verifikasi nanti:** uji refund dengan dan tanpa restock serta rekonsiliasi `stock_movements`, `refund_items`, dan jurnal.

### Tugas 9 — Satukan pembulatan HPP, biaya rata-rata, dan refund (B-06, B-07)

**P1 · Ketergantungan:** Tugas 8.

- [x] Gunakan aritmetika uang presisi tetap/integer sen dan kuantitas presisi tetap untuk mutasi stok.
- [x] Alokasikan residu pembulatan pada baris terakhir secara deterministik.
- [x] Saat stok habis, pastikan biaya pelepasan akhir menyerap sisa sen sehingga nilai persediaan menjadi nol.

**Terima bila:** jumlah HPP baris = HPP header = jurnal; jumlah baris refund = header refund; semua unit yang habis menghabiskan total biaya persediaan tepat.

**Status:** Implementasi selesai untuk transaksi baru. Nilai persediaan tersimpan dalam migrasi 017; biaya restock barang langsung mengikuti HPP yang benar-benar dilepas saat penjualan, dan refund bertahap menutup sisa biaya secara deterministik.

**Verifikasi:** lint sintaks PHP dan pemeriksaan diff. Migrasi MySQL serta skenario transaksi belum dijalankan pada database pengguna.

## Fase 3 — Laporan dan transaksi POS

### Tugas 10 — Hitung ulang laporan untuk filter produk/kategori/metode (A-06, B-05)

**P1 · Ketergantungan:** Tugas 9 untuk rekonsiliasi uang.

- [x] Hitung subtotal, diskon, HPP, kuantitas, dan refund sesuai item yang lolos filter produk/kategori.
- [x] Terapkan filter metode penjualan dan metode refund secara independen.
- [x] Pertahankan filter kasir/shift dan periode lokal tanpa menggandakan transaksi split payment.

**Status:** Implementasi selesai. Rekap panel/PDF memakai nominal baris terfilter, metode penjualan dan refund dipilih terpisah, dan penerimaan split payment tidak menggandakan transaksi. Sintaks PHP dan pemeriksaan whitespace diff valid. Skenario data MySQL dan tampilan PDF belum diverifikasi.

**Terima bila:** transaksi A Rp10.000 + B Rp20.000 difilter A menghasilkan nilai A saja; penjualan tunai dengan refund bank muncul di laporan refund berfilter bank.

**Verifikasi:** lint sintaks PHP dan pemeriksaan diff. Cocokkan tabel, total panel, dan PDF pada database MySQL untuk kombinasi filter/split payment/refund.

### Tugas 11 — Pertahankan token ulasan saat retry dan cetak ulang (A-08, B-13)

**P1 · Ketergantungan:** keputusan penyimpanan token aman pada `tasks/plan.md`.

- [ ] Retry pembayaran idempotent tidak menonaktifkan token yang sudah diterbitkan.
- [ ] Sediakan tautan/QR aktif pada struk cetak ulang tanpa menyimpan token mentah dalam database.

**Terima bila:** retry menampilkan tautan ulasan yang sama dan tautan pada cetak ulang tetap berlaku sampai kedaluwarsa/ditutup.

**Verifikasi nanti:** ulangi request finalize, buka struk lagi, dan kirim ulasan satu kali pada item transaksi.

### Tugas 12 — Batasi topping pada produk yang kompatibel (A-09)

**P1 · Ketergantungan:** aturan hubungan topping-produk/varian.

- [ ] Simpan atau definisikan kompatibilitas topping untuk produk/varian.
- [ ] Terapkan aturan sama di tampilan POS dan `SaleService`.

**Terima bila:** Air Mineral 600 ml tidak menawarkan maupun menerima topping lewat request buatan; martabak yang dikonfigurasi tetap bisa memakai topping.

**Verifikasi nanti:** cek UI POS dan submit payload yang memaksa topping tidak kompatibel.

### Tugas 13 — Terapkan aturan pembayaran dan diskon di server (B-03)

**P1 · Ketergantungan:** keputusan batas diskon dan tarif pada `tasks/plan.md`.

- [ ] Tolak lebih dari dua metode pembayaran, alokasi tidak tepat, dan diskon di luar kebijakan owner.
- [ ] Tambahkan diskon nominal/persentase item dan transaksi dengan alokasi deterministik.
- [ ] Terapkan biaya layanan/pajak hanya jika konfigurasi diaktifkan; default nonaktif.

**Terima bila:** total sesuai rumus PRD, total tidak negatif, uang Rupiah dibulatkan half-up, dan request langsung tidak dapat melewati aturan UI.

**Verifikasi nanti:** AC-04/05 dan kasus batas diskon, alokasi split, pajak, serta layanan.

### Tugas 14 — Lengkapi siklus keranjang dan detail pesanan (B-02)

**P2 · Ketergantungan:** Tugas 13.

- [ ] Tambahkan draft/hold, lanjutkan, dan batal sebelum lunas tanpa mutasi stok/jurnal.
- [ ] Simpan jenis pesanan, nomor meja/nama pelanggan opsional, dan catatan tiap item.
- [ ] Periksa ulang stok terkini saat pembayaran, bukan ketika keranjang ditahan.

**Terima bila:** hold tidak mereservasi stok; transaksi dibatalkan tidak menulis penjualan; pembayaran tetap menyimpan snapshot item/pesanan.

**Verifikasi nanti:** skenario hold, perubahan stok saat hold, batal, dan lanjut bayar.

## Fase 4 — Kelengkapan operasional

### Tugas 15 — Tampilkan ketersediaan produk di katalog dan POS (B-09)

**P2 · Ketergantungan:** aturan hitung ketersediaan resep dan barang siap jual.

- [ ] Hitung status tersedia/habis memakai stok yang relevan tanpa memublikasikan jumlah atau nilai internal.
- [ ] POS memberikan status dan alasan ketika produk habis atau terkunci opname.

**Terima bila:** stok habis tidak ditampilkan tersedia di katalog/POS dan finalisasi tetap memvalidasi ulang.

**Verifikasi nanti:** produk recipe, direct stock, non-stock, dan stok terkunci.

### Tugas 16 — Tambahkan alur penerimaan kasir dengan persetujuan biaya (B-04)

**P2 · Ketergantungan:** skema status persetujuan dan role.

- [ ] Izinkan kasir melihat stok/kartu stok dan menyusun penerimaan sesuai kebijakan.
- [ ] Owner menyetujui item/biaya sebelum kasir dapat mem-posting; perubahan yang belum disetujui tidak memengaruhi stok/jurnal.

**Terima bila:** kasir dapat melihat operasional yang diizinkan; biaya yang belum disetujui tidak bisa diposting lewat UI atau URL langsung.

**Verifikasi nanti:** AC-15 dan akses langsung untuk kasir/owner.

### Tugas 17 — Tambahkan saldo awal, kerusakan, koreksi, dan pembalik stok (B-04)

**P2 · Ketergantungan:** valuasi stok/jurnal dari Tugas 8–9.

- [ ] Sediakan alur saldo awal dengan biaya dan akun lawan yang seimbang.
- [ ] Sediakan pencatatan kerusakan/koreksi dengan alasan dan persetujuan sesuai role.
- [ ] Mutasi ter-posting tidak diedit atau dihapus; koreksi memakai mutasi pembalik yang terlacak.

**Terima bila:** setiap perubahan stok punya sumber, pelaksana, waktu, jumlah, biaya, dan alasan.

**Verifikasi nanti:** rekonsiliasi kartu stok ke akun persediaan setelah setiap jenis mutasi.

### Tugas 18 — Buat audit trail tindakan sensitif (B-14)

**P2 · Ketergantungan:** daftar event dan struktur audit.

- [ ] Sediakan tabel/layanan audit append-only dengan pengguna, tindakan, objek, waktu, alasan, dan nilai perubahan relevan.
- [ ] Catat perubahan harga/resep, stok/penerimaan, refund, pengeluaran, jurnal, penutupan periode, akun, dan moderasi.
- [ ] Jangan simpan password atau token ulasan mentah.

**Terima bila:** owner dapat menelusuri perubahan sensitif dan pengguna biasa tidak dapat mengubah/menghapus riwayat audit.

**Verifikasi nanti:** lakukan contoh tiap event dan periksa kelengkapan tanpa data rahasia.

### Tugas 19 — Sinkronkan struk dengan pengaturan usaha (B-13)

**P2 · Ketergantungan:** Tugas 11.

- [ ] Gunakan identitas usaha dari pengaturan tersimpan.
- [ ] Sediakan QR/tautan ulasan dan cetak ulang, serta format cetak 58/80 mm dan PDF bila tetap masuk lingkup PRD.

**Terima bila:** perubahan nama/alamat usaha muncul konsisten pada struk; cetak ulang memiliki detail/tautan yang benar.

**Verifikasi nanti:** pratinjau dan cetak browser untuk ukuran yang didukung.

### Tugas 20 — Lengkapi pengelolaan foto produk dan banner (B-14)

**P2 · Ketergantungan:** tidak ada.

- [ ] Foto produk dapat diganti, diurutkan, dan dihapus dengan batas jumlah yang ditetapkan.
- [ ] Banner mendukung gambar baru saat edit dan pratinjau desktop/mobile sebelum simpan.

**Terima bila:** file lama dibersihkan dengan aman, urutan konsisten pada publik, validasi ukuran/tipe tetap berlaku.

**Verifikasi nanti:** uji CRUD media dan banner nonaktif/di luar jadwal.

## Fase 5 — Tampilan dan kesiapan rilis

### Tugas 21 — Perbaiki drawer keyboard dan breakpoint POS/sidebar (B-10, B-11)

**P2 · Ketergantungan:** tidak ada.

- [ ] Saat drawer tertutup, kontrolnya tidak bisa difokuskan dan tersembunyi dari teknologi bantu.
- [ ] Saat drawer terbuka, fokus masuk, tetap di dalam drawer, dan kembali ke tombol pembuka saat ditutup.
- [ ] Sesuaikan POS untuk breakpoint tablet dan bottom sheet ponsel sesuai desain.

**Terima bila:** tidak ada fokus Tab pada kontrol drawer tertutup; layout sesuai pada 360, 768, 1024, dan 1440 px.

**Verifikasi nanti:** keyboard-only, pohon aksesibilitas browser, dan viewport desain.

### Tugas 22 — Buat tabel panel nyaman dipakai di ponsel (B-12)

**P2 · Ketergantungan:** tidak ada.

- [ ] Tabel prioritas menampilkan data menjadi baris berlabel pada ponsel atau memakai solusi responsif yang setara.
- [ ] Header kolom tetap terbaca saat daftar digulir.

**Terima bila:** data inti tidak memerlukan gulir horizontal pada lebar ponsel; tabel besar tetap dapat dibandingkan di desktop.

**Verifikasi nanti:** viewport 360/768/1024/1440 px dan pembesaran teks.

### Tugas 23 — Paginasi dan ekspor laporan besar (B-14)

**P2 · Ketergantungan:** Tugas 10.

- [ ] Paginasikan detail pada panel dan hindari membangun `IN (...)` dengan semua ID histori.
- [ ] Terapkan batas aman atau pekerjaan latar dengan tautan privat untuk PDF besar.

**Terima bila:** laporan 100.000 transaksi tidak memuat seluruh baris ke memori request; total tetap sama dengan query agregat dan PDF.

**Verifikasi nanti:** ukur dataset target dan target PRD untuk PDF tahunan.

### Tugas 24 — Konfigurasikan base URL dan pengamanan deployment (B-16)

**P1 sebelum publikasi · Ketergantungan:** alamat host/domain dan topologi proxy.

- [ ] Baca base URL dari konfigurasi deployment, bukan `localhost` tetap.
- [ ] Aktifkan HTTPS, cookie aman, dan header yang sesuai hanya pada lingkungan HTTPS; pertahankan HTTP untuk LAN bila diperlukan.
- [ ] Pastikan URL QR dapat dijangkau pelanggan dan header proxy tidak dipercaya tanpa konfigurasi.

**Terima bila:** login, sesi, upload, POS, tautan ulasan, dan PDF bekerja di HTTP lokal/LAN dan HTTPS deployment tanpa mixed content/redirect loop.

**Verifikasi nanti:** konfigurasi aktual web server/proxy serta pemeriksaan header/cookie.

### Tugas 25 — Tandai data demo dan rapikan teks progres (A-10, B-17)

**P1 sebelum publikasi · Ketergantungan:** tidak ada.

- [ ] Pastikan alamat, nomor telepon, ulasan, banner, dan nama demo diberi penanda demo atau diganti data usaha terverifikasi sebelum publikasi.
- [ ] Perbarui label tahap pada dashboard agar sesuai fitur yang tersedia.

**Terima bila:** tidak ada data seeder yang disajikan sebagai pengalaman pelanggan nyata pada situs publik.

**Verifikasi nanti:** buka beranda dengan database demo dan database produksi yang telah disiapkan.

### Tugas 26 — Selesaikan UAT, backup/restore, dan panduan (Tahap 6)

**P1 sebelum publikasi · Ketergantungan:** tugas 1–25.

- [ ] Buat tes unit/integrasi aturan pembayaran, refund, stok, jurnal, role, dan laporan.
- [ ] Jalankan AC-01–AC-25 di MySQL, termasuk concurrency, rollback, PDF multi-halaman, dan akses route langsung.
- [ ] Dokumentasikan build/deploy, owner/kasir, backup harian, retensi, dan pemulihan.
- [ ] Pulihkan backup ke lingkungan uji dan cocokkan transaksi, jurnal, stok, serta lampiran.

**Terima bila:** hasil UAT tercatat; target kehilangan data/pemulihan PRD diuji; seluruh temuan P1 ditutup atau memiliki keputusan ruang lingkup tertulis.
