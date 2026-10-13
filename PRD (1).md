# PRD — Sistem Informasi Martabak

**Versi:** 1.0  
**Tanggal:** 13 Oktober 2026  
**Status:** Spesifikasi awal siap menjadi acuan desain dan pengembangan  
**Platform:** Aplikasi web responsif  
**Teknologi utama:** CodeIgniter 4, Tailwind CSS, MySQL/MariaDB  
**Bahasa, mata uang, zona waktu:** Bahasa Indonesia, Rupiah (IDR), Asia/Jakarta

## 1. Ringkasan produk

Sistem informasi untuk usaha martabak yang menggabungkan katalog publik, POS kasir, pengelolaan produk dan bahan baku, stok opname, pengeluaran, akuntansi owner, banner promosi, serta rating dan komentar bergambar. Owner dan kasir menggunakan panel sesuai hak akses. Pelanggan (customer) mengakses halaman publik tanpa membuat akun atau login.

Sistem menyediakan laporan penjualan dan laporan operasional/keuangan sesuai role, dengan ekspor PDF harian, bulanan, dan tahunan. Identitas visual menggunakan warna dasar kuning.

## 2. Tujuan dan ukuran keberhasilan

| Tujuan | Kriteria keberhasilan awal |
| --- | --- |
| Mempercepat transaksi | Kasir dapat menyelesaikan transaksi 3 item dalam maksimal 60 detik setelah pelatihan, di luar waktu pelanggan membayar |
| Menyatukan pencatatan | Setiap transaksi lunas menghasilkan catatan pembayaran, mutasi stok, dan jurnal tepat satu kali |
| Memperjelas persediaan | Setiap perubahan stok memiliki sumber, waktu, pelaksana, jumlah, dan alasan |
| Mempermudah kontrol usaha | Owner dapat melihat penjualan, HPP, pengeluaran, laba rugi, serta saldo kas/bank |
| Mempermudah pelaporan | Total PDF sama dengan total panel untuk filter dan waktu pengambilan data yang sama |
| Mengumpulkan umpan balik | Pelanggan dapat memberi rating, komentar, dan foto melalui tautan ulasan tanpa login |

Angka performa merupakan target penerimaan yang harus diuji, bukan klaim kemampuan sistem yang sudah tersedia.

## 3. Ruang lingkup dan asumsi

### 3.1 Cakupan rilis pertama

- Satu outlet, satu lokasi stok, beberapa akun kasir, dan satu atau lebih akun owner.
- Tiga role bisnis: owner, kasir, dan customer tamu. Customer tidak memiliki akun autentikasi.
- POS berbasis web dengan transaksi tunai, QRIS, dan transfer yang dikonfirmasi manual oleh kasir.
- Produk martabak berbasis resep serta barang siap jual, misalnya minuman kemasan.
- Stok bahan baku, stok barang siap jual, penerimaan barang, penyesuaian, dan opname.
- Akuntansi operasional berbasis jurnal berpasangan, buku besar, neraca saldo, laba rugi, neraca, serta penerimaan/pengeluaran kas dan bank.
- Halaman publik, banner promosi, dan ulasan bergambar dengan moderasi.
- Laporan PDF berdasarkan periode harian, bulanan, dan tahunan.

### 3.2 Di luar cakupan rilis pertama

- Pemesanan online mandiri, delivery, integrasi marketplace, dan pelacakan kurir.
- Payment gateway, validasi QRIS otomatis, dan rekonsiliasi bank otomatis.
- Multi-outlet, multi-gudang, akuntansi konsolidasi, penggajian, dan pelaporan pajak resmi.
- Transaksi kredit, piutang pelanggan, dan pembelian berutang; transaksi awal diasumsikan langsung dibayar.
- Mode offline dengan sinkronisasi. Jika server tidak terhubung, transaksi tidak dianggap tersimpan.
- Program loyalitas, voucher kompleks, dan integrasi langsung perangkat printer khusus.

### 3.3 Keputusan default yang dapat disesuaikan

1. Customer melihat katalog dan mengulas tanpa login; seluruh penjualan dicatat melalui kasir.
2. Owner mengelola akun, harga, resep, banner, moderasi ulasan, dan kebijakan diskon.
3. Kasir dapat mengajukan pengeluaran, opname, refund, dan koreksi stok; tindakan berdampak keuangan tertentu memerlukan persetujuan owner.
4. Pembayaran gabungan didukung maksimal dua metode per transaksi. QRIS/transfer dicatat sebagai penerimaan rekening yang ditetapkan, tanpa klaim verifikasi otomatis.
5. Pajak dan biaya layanan nonaktif secara default. Jika diaktifkan, nilainya dikonfigurasi owner dan ditampilkan terpisah.
6. Stok negatif dilarang. Resep wajib untuk produk yang dibuat berdasarkan bahan baku.

## 4. Role dan hak akses

Otorisasi wajib diterapkan di server untuk setiap route dan operasi, bukan hanya menyembunyikan menu.

| Fitur/tindakan | Owner | Kasir | Customer tanpa login |
| --- | --- | --- | --- |
| Katalog, banner, ulasan terbit | Lihat | Lihat | Lihat |
| Kirim rating, komentar, foto | Melalui alur pelanggan | Melalui alur pelanggan | Ya, dengan token ulasan transaksi |
| Login dan panel | Ya | Ya | Tidak |
| Dashboard keuangan dan akuntansi | Penuh | Tidak | Tidak |
| POS dan buka/tutup shift | Tidak secara default | Ya | Tidak |
| Kelola kategori, produk, varian, resep, harga | Penuh | Lihat data operasional | Tidak |
| Lihat stok dan riwayat mutasi | Ya | Ya | Hanya status tersedia/habis |
| Input penerimaan barang | Ya | Ya, berdasarkan item dan biaya yang sudah diotorisasi owner | Tidak |
| Buat dan hitung opname | Ya | Ya | Tidak |
| Setujui/posting selisih opname | Ya | Tidak | Tidak |
| Koreksi stok di luar opname | Setujui dan posting | Ajukan | Tidak |
| Laporan penjualan dan PDF | Semua | Semua penjualan outlet; tanpa HPP/laba | Tidak |
| Detail transaksi | Semua | Semua transaksi outlet, data operasional saja | Tidak |
| Refund transaksi lunas | Setujui | Ajukan dan jalankan persetujuan | Tidak |
| Pengeluaran | Kelola, setujui, bayar, laporkan | Ajukan; lihat pengajuan sendiri | Tidak |
| Banner dan moderasi ulasan | Penuh | Tidak | Tidak |
| Akun, pengaturan, periode buku, audit | Penuh | Tidak | Tidak |

Kasir tidak boleh membaca HPP, buku besar, saldo modal, atau laporan laba melalui API maupun URL langsung. Jika owner perlu menjalankan POS, gunakan akun kasir terpisah agar jejak tindakan jelas.

## 5. Kebutuhan fungsional

### FR-01 — Autentikasi dan panel

- Login owner/kasir menggunakan username atau email dan password.
- Password di-hash; sesi diperbarui setelah login dan diakhiri saat logout.
- Owner dapat membuat, menonaktifkan, dan mereset password kasir. Akun nonaktif kehilangan akses sesi aktif.
- Tidak ada registrasi owner secara publik. Akun owner pertama dibuat melalui proses instalasi terkontrol.
- Panel menyediakan sidebar sesuai role, identitas pengguna, tanggal, notifikasi stok minimum, dan tombol logout.
- Dashboard owner: penjualan bersih, jumlah transaksi, HPP, laba kotor, pengeluaran, saldo kas/bank, stok menipis, dan produk terlaris.
- Dashboard kasir: shift aktif, transaksi hari ini, penjualan hari ini, stok menipis, dan akses cepat POS.
- Satu akun kasir hanya memiliki satu shift aktif; beberapa kasir dapat bekerja pada shift masing-masing.

### FR-02 — Katalog publik dan identitas usaha

- Halaman beranda menampilkan logo/nama usaha, banner aktif, kategori, produk populer, jam buka, alamat, dan kontak.
- Katalog mendukung pencarian dan filter kategori serta status ketersediaan.
- Detail produk menampilkan foto, deskripsi, varian/ukuran, harga, rating rata-rata, komentar, dan foto ulasan yang disetujui.
- Harga menggunakan format Rupiah. Stok internal dan data keuangan tidak dipublikasikan.
- Produk nonaktif tidak muncul di katalog maupun pilihan transaksi baru.
- Pada rilis pertama tidak ada tombol checkout online; halaman dapat mengarahkan pelanggan untuk membeli di outlet atau menghubungi usaha.

### FR-03 — Kelola produk (owner)

- CRUD kategori, produk, varian, topping, foto, SKU, deskripsi, harga jual, status aktif, dan urutan tampil.
- Jenis persediaan produk: `recipe` (berbahan baku), `finished_good` (stok siap jual), atau `non_stock` untuk item yang memang tidak dilacak persediaannya.
- Setiap varian berbasis resep memiliki daftar bahan dan takaran. Topping dapat menambah harga dan konsumsi bahan.
- Bahan menggunakan satuan dasar, misalnya gram, mililiter, atau pcs. Konversi pembelian harus eksplisit, misalnya 1 kg = 1.000 gram; konversi massa ke volume tidak otomatis.
- Perubahan harga, nama, resep, dan biaya tidak mengubah snapshot transaksi lama.
- Produk yang sudah dipakai transaksi tidak dihapus permanen; gunakan nonaktif/arsip.
- Foto produk: JPEG/PNG/WebP, maksimal 2 MB per foto, maksimal 5 foto per produk.

### FR-04 — Stok produk dan bahan baku

- Owner dan kasir dapat melihat stok tersedia, satuan, stok minimum, dan kartu stok.
- Owner mengatur master bahan, supplier sederhana, biaya penerimaan, konversi satuan, saldo awal, dan batas minimum stok.
- Penerimaan barang mencatat nomor referensi, supplier opsional, tanggal, item, kuantitas, biaya satuan, sumber pembayaran, bukti opsional, dan petugas.
- Kasir hanya memposting penerimaan dengan referensi pembelian yang biaya dan itemnya telah disetujui owner; perubahan biaya harus dikembalikan untuk persetujuan.
- Penerimaan meningkatkan stok dan nilai persediaan. Biaya barang tidak langsung dibukukan sebagai pengeluaran operasional.
- Penjualan lunas mengurangi bahan sesuai snapshot resep atau stok barang siap jual. Item `non_stock` tidak menghasilkan mutasi persediaan.
- Perkiraan porsi produk resep dihitung dari bahan pembatas. Untuk beberapa varian berbagi bahan, angka per varian tidak boleh dijumlahkan sebagai total stok independen.
- Setiap mutasi mempunyai tipe: saldo awal, penerimaan, penjualan, retur layak stok, kerusakan, opname, atau koreksi.
- Mutasi yang sudah diposting tidak diubah/dihapus; koreksi dilakukan melalui mutasi pembalik dan catatan alasan.
- Penilaian stok memakai rata-rata bergerak per item. HPP penjualan dikunci saat transaksi diposting.
- Entri mundur yang mengubah urutan penilaian stok tidak diperbolehkan pada rilis pertama; tanggal dokumen lama boleh dicatat terpisah dari tanggal posting.

### FR-05 — Stok opname (owner dan kasir)

1. Petugas membuat sesi opname untuk seluruh atau sebagian item dan mencatat waktu cut-off.
2. Sistem mengunci mutasi item yang diperiksa selama sesi penghitungan; POS menolak pembayaran keranjang yang membutuhkan item terkunci dengan pesan yang jelas.
3. Sistem menyimpan stok sistem pada cut-off; petugas memasukkan jumlah fisik, catatan, dan bukti opsional.
4. Selisih = stok fisik − stok sistem. Jumlah negatif berarti kekurangan; positif berarti kelebihan.
5. Kasir mengirim hasil untuk ditinjau owner. Owner dapat meminta koreksi, menolak, atau menyetujui.
6. Persetujuan memposting mutasi penyesuaian dan jurnal selisih tepat satu kali, lalu membuka kunci stok.
7. Pembatalan sesi membuka kunci tanpa mengubah saldo. Sistem menampilkan sesi lama yang belum selesai agar tidak terlupakan.

Status: `draft → counting → submitted → approved/rejected`; `draft/counting/submitted` dapat menjadi `cancelled` dengan alasan. Penolakan mengembalikan sesi untuk koreksi sebelum diajukan ulang. Sesi disetujui tidak dapat diedit. Owner dapat menghitung dan menyetujui sendiri untuk usaha kecil; sistem mencatat identitas yang sama pada kedua tindakan.

### FR-06 — POS lengkap (kasir)

**Sebelum transaksi**

- Kasir membuka shift dengan modal kas awal dan saldo laci. Modal awal merupakan perpindahan kas, bukan pendapatan.
- POS menampilkan grid produk, kategori, pencarian nama/SKU, varian, topping, harga, dan status tersedia.

**Keranjang dan perhitungan**

- Tambah/hapus item, ubah kuantitas, pilih varian/topping, serta catatan seperti “tanpa kacang”.
- Jenis pesanan: makan di tempat atau bawa pulang; nama pelanggan dan nomor meja opsional.
- Simpan/tahan keranjang dan lanjutkan kembali. Keranjang tertahan tidak mereservasi stok; stok dicek ulang saat pembayaran.
- Diskon nominal/persentase per item atau transaksi mengikuti batas owner. Diskon melebihi batas harus disetujui owner.
- Subtotal = jumlah (harga dasar varian + harga topping) × kuantitas.
- Diskon item diterapkan terlebih dahulu, kemudian diskon transaksi dialokasikan proporsional ke nilai item setelah diskon item.
- Dasar biaya layanan = subtotal setelah seluruh diskon; dasar pajak = subtotal setelah diskon + biaya layanan, jika konfigurasi pajak aktif.
- Total bayar = subtotal − seluruh diskon + biaya layanan + pajak. Konfigurasi dasar pajak harus didokumentasikan dan dikonfirmasi owner sebelum dipakai.
- Total/diskon tidak boleh menghasilkan angka negatif. Nilai Rupiah dibulatkan ke Rupiah terdekat dengan aturan half-up; selisih alokasi pembulatan ditempatkan pada item terakhir secara deterministik.

**Pembayaran dan struk**

- Metode: tunai, QRIS manual, transfer manual, atau gabungan maksimal dua metode.
- Kasir memasukkan uang diterima untuk tunai; sistem menghitung kembalian. Non-tunai tidak menghasilkan kembalian.
- Jumlah pembayaran yang dialokasikan harus sama dengan total tagihan. Kelebihan uang tunai dicatat terpisah sebagai kembalian.
- Non-tunai memerlukan konfirmasi kasir dan referensi/bukti opsional sesuai kebijakan outlet; tampilkan label “dikonfirmasi manual”.
- Finalisasi pembayaran menyimpan transaksi, pembayaran, mutasi stok, HPP, dan jurnal dalam satu transaksi database.
- Gunakan idempotency key dan penguncian saldo stok untuk mencegah transaksi ganda atau overselling akibat klik ulang dan dua kasir bersamaan.
- Nomor transaksi unik. Struk berisi identitas usaha, waktu, nomor, kasir, item, topping, diskon, pajak/layanan, pembayaran, kembalian, serta QR/tautan ulasan.
- Cetak struk melalui browser dengan layout 58/80 mm dan opsi PDF struk. Kegagalan mencetak tidak membatalkan transaksi; tersedia cetak ulang.

**Pembatalan, refund, dan shift**

- Keranjang yang belum lunas boleh dibatalkan kasir dengan alasan, tanpa perubahan stok atau jurnal.
- Transaksi lunas tidak boleh diedit. Koreksi menggunakan refund penuh/sebagian dengan persetujuan owner, alasan, item, jumlah, nominal, dan metode pengembalian.
- Jumlah refund kumulatif tidak boleh melebihi kuantitas maupun nilai awal, termasuk alokasi diskon, pajak, dan biaya layanan yang terkait.
- Martabak yang sudah dibuat tidak otomatis mengembalikan bahan ke stok. HPP tetap menjadi biaya; barang siap jual yang benar-benar kembali layak jual dapat menambah stok dan membalik HPP berdasarkan biaya awal transaksi.
- Status keuangan: `draft/held → paid → partially_refunded/refunded`; `draft/held → cancelled`. Tidak ada penghapusan transaksi lunas.
- Kasir menutup shift dengan uang fisik. Sistem membandingkan kas seharusnya dengan kas aktual dan mencatat selisih beserta alasan.
- Kas seharusnya = modal awal + penjualan tunai neto kembalian − refund tunai − pengeluaran dari laci + kas masuk lain − setoran/penarikan laci.
- Selisih shift memerlukan tinjauan owner sebelum penyesuaian akuntansi diposting. Shift yang ditutup tidak bisa menerima transaksi baru.

### FR-07 — Kelola pengeluaran

- Kategori awal: sewa, listrik/air, gas, transportasi, kebersihan, pemasaran, dan lain-lain; owner dapat menyesuaikan.
- Form: tanggal dokumen, tanggal posting, kategori/akun, deskripsi, nominal positif, rekening/laci sumber, penerima opsional, serta foto/PDF bukti maksimal 5 MB.
- Status: `draft → submitted → approved → paid`, dengan `rejected/cancelled` sebelum pembayaran.
- Kasir mengajukan; owner menyetujui dan mencatat pembayaran. Owner dapat membuat dan langsung membayar dengan jejak persetujuan sendiri.
- Hanya status `paid` yang mengurangi kas/bank dan masuk buku besar. Persetujuan saja belum memposting jurnal pada rilis ini.
- Jika sumber pembayaran laci kasir, wajib terkait shift aktif dan langsung masuk perhitungan saldo laci.
- Pembelian stok dicatat melalui penerimaan/pembelian persediaan; tidak boleh dicatat lagi sebagai biaya operasional.
- Pengeluaran dibayar tidak dapat dihapus. Pembatalan setelah pembayaran menggunakan jurnal pembalik dan bukti pengembalian dana bila ada.
- Tersedia filter periode, kategori, petugas, status, dan sumber dana; laporan pengeluaran hanya untuk owner.

### FR-08 — Akuntansi (owner)

**Cakupan**

- Daftar akun awal: kas utama, laci kasir, bank/QRIS, persediaan, aset lain, kewajiban awal, modal, prive, penjualan, retur/diskon penjualan, pendapatan layanan, HPP, beban operasional, selisih stok, selisih kas, dan pajak terutang jika digunakan.
- Saldo awal akun dan persediaan harus seimbang sebelum mulai operasional; saldo persediaan akun harus sama dengan buku pembantu stok.
- Jurnal otomatis terhubung ke transaksi sumber dan jurnal manual terbatas owner dengan alasan/bukti.
- Total debit wajib sama dengan kredit; nominal memakai tipe desimal atau integer Rupiah, tidak memakai floating point untuk uang.
- Setiap transaksi sumber memiliki kunci unik agar jurnal tidak diposting dua kali.

**Pemetaan jurnal minimum**

| Kejadian | Debit | Kredit |
| --- | --- | --- |
| Penjualan lunas | Kas/laci/bank sesuai alokasi | Penjualan neto diskon, pendapatan layanan, pajak terutang jika aktif |
| Konsumsi bahan/barang | HPP | Persediaan |
| Pembelian stok dibayar | Persediaan | Kas/bank |
| Pengeluaran operasional dibayar | Beban sesuai kategori | Kas/laci/bank |
| Kekurangan opname | Beban selisih stok | Persediaan |
| Kelebihan opname | Persediaan | Pendapatan/penyesuaian selisih stok |
| Modal masuk | Kas/bank | Modal |
| Prive | Prive | Kas/bank |
| Pemindahan uang ke laci | Laci kasir | Kas utama |
| Refund penjualan | Retur penjualan dan pembalikan layanan/pajak terkait | Kas/bank |
| Retur barang yang layak stok | Persediaan, sebesar HPP awal | HPP |

- Opname memakai biaya rata-rata saat cut-off. Jika item belum memiliki biaya, persetujuan memerlukan biaya valid dari owner.
- Refund tidak memulihkan bahan martabak yang sudah dikonsumsi. Jurnal HPP hanya dibalik untuk retur stok fisik yang layak.
- Jurnal manual langsung ke akun persediaan/laci dibatasi: gunakan alur stok/shift agar buku pembantu tetap cocok.
- Tersedia jurnal umum, buku besar, neraca saldo, laba rugi, neraca, dan laporan penerimaan/pengeluaran kas per akun; laporan kas ini bukan laporan arus kas formal otomatis.
- Laba kotor = penjualan bersih produk − HPP. Laba bersih operasional = laba kotor + pendapatan layanan + pendapatan operasional lain − beban operasional, termasuk selisih yang dibukukan.
- Pajak yang dipungut bukan pendapatan. Modal dan prive tidak dihitung sebagai pendapatan/beban.
- Owner dapat menutup periode bulanan. Semua posting ke periode tertutup ditolak; koreksi dilakukan pada periode terbuka dengan referensi asal. Pembukaan kembali hanya oleh owner dengan alasan dan audit.

### FR-09 — Laporan penjualan dan PDF

- Owner dan kasir dapat memilih periode harian (satu tanggal), bulanan (bulan dan tahun), serta tahunan (tahun).
- Rentang menggunakan waktu lokal Asia/Jakarta: awal periode inklusif sampai awal periode berikutnya eksklusif.
- Laporan menyajikan jumlah transaksi lunas, unit terjual, omzet sebelum diskon, diskon, retur/refund, penjualan bersih, pajak, layanan, penerimaan per metode, dan produk terlaris.
- Filter tambahan: kasir, produk, kategori, metode pembayaran, dan shift. Filter pembayaran tidak boleh menduplikasi total transaksi dengan pembayaran gabungan.
- Penjualan mengikuti waktu pembayaran berhasil. Refund mengikuti waktu refund dibukukan dan menunjukkan nomor transaksi asal, termasuk bila penjualan berasal dari periode sebelumnya.
- Draft, held, cancelled, pengajuan pengeluaran, dan opname belum disetujui tidak masuk nilai finansial.
- Detail dan rekap dapat diekspor PDF A4. Laporan stok/opname, pengeluaran, shift, dan keuangan tersedia untuk role yang berhak pada periode harian, bulanan, serta tahunan.
- Laporan neraca dan saldo stok menampilkan posisi akhir periode; laporan mutasi, penjualan, dan laba rugi menampilkan aktivitas selama periode.
- PDF memuat logo/nama usaha, judul, periode, filter, waktu dibuat, petugas pencetak, tabel, total, dan nomor halaman.
- Kasir hanya menerima data penjualan/operasional; PDF kasir tidak memuat HPP, laba, buku besar, atau laporan pengeluaran menyeluruh.
- Ekspor memakai snapshot data konsisten agar perubahan bersamaan tidak membuat total dan detail berbeda.
- Jika data kosong, panel dan PDF menampilkan “Tidak ada data pada periode ini” dengan total nol.
- Tabel panjang harus memiliki header berulang, tidak terpotong horizontal, dan rekap total yang jelas.

### FR-10 — Upload dan kelola banner

- Owner dapat menambah, mengganti, mengarsipkan, mengurutkan, serta mengaktifkan/nonaktifkan banner.
- Field: judul internal, gambar, teks alternatif, tautan opsional, urutan, tanggal mulai/selesai opsional, status.
- JPEG/PNG/WebP maksimal 3 MB, rasio rekomendasi 16:5; pratinjau desktop dan mobile tersedia sebelum simpan.
- Hanya banner aktif dalam rentang jadwal yang tampil. Jika tidak ada, tampilkan hero bawaan dengan nama usaha.
- Tautan hanya menerima URL relatif internal atau skema HTTP/HTTPS; tolak skema berbahaya.
- Carousel memiliki kontrol manual dan tidak mengganggu penggunaan keyboard.

### FR-11 — Rating dan komentar bergambar tanpa login

- Pelanggan mengakses tautan/QR token acak dari struk transaksi lunas, tanpa akun dan tanpa password.
- Token minimal setara 128 bit entropi, disimpan dalam bentuk hash, tidak berisi data pribadi, dan kedaluwarsa setelah 30 hari sebagai default.
- Satu ulasan per baris produk transaksi; pengiriman menggunakan token tidak membuka riwayat pelanggan maupun detail transaksi lain.
- Field: nama tampilan/anonim, rating integer 1–5, komentar 10–1.000 karakter, dan hingga 3 foto opsional.
- Foto JPEG/PNG/WebP maksimal 2 MB per foto. Metadata lokasi dihapus saat pemrosesan gambar.
- Status: `pending → approved/rejected`; ulasan disetujui dapat disembunyikan dengan alasan yang tercatat.
- Owner memoderasi spam, kata kasar, data pribadi, dan foto tidak relevan. Owner tidak boleh mengubah nilai rating atau isi asli pelanggan.
- Hanya ulasan approved yang tampil dan dihitung dalam rata-rata. Tampilkan jumlah ulasan bersama rating.
- Pembatasan percobaan berdasarkan token dan IP, honeypot, serta CAPTCHA adaptif jika ditemukan penyalahgunaan.
- Tampilkan pemberitahuan bahwa nama tampilan, komentar, dan foto yang disetujui akan tampil publik.
- Refund penuh menonaktifkan token yang belum digunakan; ulasan yang telah masuk tetap disimpan dan diberi penanda internal refund untuk moderasi, bukan otomatis dihapus karena nilai buruk.

## 6. Desain UI/UX

### 6.1 Identitas visual

| Elemen | Nilai awal |
| --- | --- |
| Warna utama | Kuning `#FACC15` |
| Hover/aksen | `#EAB308` |
| Latar lembut | `#FEFCE8` |
| Permukaan kartu | Putih `#FFFFFF` |
| Teks utama di atas kuning | Gelap `#111827` |
| Teks sekunder | `#4B5563` |
| Sukses / peringatan / gagal | Hijau / amber / merah disertai ikon dan teks |

- Gunakan Tailwind CSS dengan token warna terpusat; tombol kuning menggunakan teks gelap.
- Layout bersih, kartu ringkas, sudut membulat, angka keuangan rata kanan, dan status konsisten.
- Panel desktop: sidebar, topbar, breadcrumb, judul, filter, konten. Pada mobile sidebar menjadi drawer.
- POS desktop/tablet: daftar produk di kiri dan keranjang di kanan. Mobile memakai panel keranjang yang mudah dibuka.
- Form memiliki label jelas, pesan kesalahan per field, keadaan loading, kosong, sukses, dan gagal.
- Tombol sentuh ditargetkan minimal 44 × 44 px, navigasi keyboard dapat digunakan, dan fokus terlihat.
- Konfirmasi eksplisit diperlukan untuk posting opname, refund, penutupan periode, dan pembatalan final.
- Tampilan diujikan pada lebar 360, 768, 1.024, dan 1.440 px; tabel besar boleh scroll horizontal dengan judul kolom tetap jelas.

### 6.2 Struktur halaman

| Area | Halaman |
| --- | --- |
| Publik | Beranda, katalog, detail produk, formulir ulasan token, informasi usaha |
| Autentikasi | Login, ubah password |
| Owner | Dashboard, produk/kategori/varian/resep, bahan/supplier, stok/penerimaan, opname, transaksi, laporan penjualan, pengeluaran, akuntansi, banner, ulasan, akun kasir, pengaturan, audit |
| Kasir | Dashboard, POS, shift, transaksi/struk/refund, stok/penerimaan terotorisasi, opname, pengajuan pengeluaran, laporan penjualan |

## 7. Arsitektur dan teknologi

Bagian ini merupakan keputusan rancangan. Versi minor dan kompatibilitas dependency harus diverifikasi serta dikunci pada awal implementasi.

| Lapisan | Pilihan |
| --- | --- |
| Backend | PHP dan CodeIgniter 4 dengan pola MVC |
| Antarmuka | CodeIgniter Views, Tailwind CSS yang dikompilasi, JavaScript untuk interaksi POS/filter |
| Database | MySQL atau MariaDB dengan engine transaksi dan dukungan penguncian baris; pilih satu untuk deployment |
| Akses data | Model/query builder, migrasi, seeder, validasi server |
| Autentikasi | Session server-side dan filter autentikasi/role CodeIgniter |
| PDF | Dompdf melalui Composer dengan template cetak HTML/CSS terpisah yang diuji |
| Build | Composer untuk PHP; Node.js dan pipeline Tailwind untuk aset |
| Web server | Apache atau Nginx; document root diarahkan ke direktori `public` |
| Penyimpanan | File privat di luar public; gambar publik dilayani melalui URL terkontrol |
| Pengujian | Unit test aturan perhitungan, integration test transaksi/database, dan pengujian alur UI |

- Controller menangani request, service menangani aturan bisnis, model/repository menangani penyimpanan, view menangani tampilan.
- Pisahkan service penjualan, stok, opname, jurnal, refund, pengeluaran, dan pelaporan agar posting konsisten.
- Gunakan transaksi database untuk semua tindakan yang memengaruhi lebih dari satu ledger.
- Tidak boleh bergantung pada Tailwind CDN saat produksi; aset dibangun dan disimpan lokal agar panel LAN tetap dapat dimuat.
- Template PDF tidak bergantung pada seluruh utility Tailwind; buat stylesheet cetak yang kompatibel dengan renderer.
- Struktur konseptual: `Controllers/Public`, `Controllers/Panel`, `Services`, `Models`, `Filters`, `Views`, `Database/Migrations`, `Database/Seeds`, dan `Tests`.

## 8. Dukungan HTTP dan HTTPS

Satu codebase harus dapat dipasang pada kedua skema melalui konfigurasi lingkungan, tanpa mengubah source code.

| Mode | Perilaku |
| --- | --- |
| HTTP lokal/LAN | `baseURL` memakai HTTP, redirect HTTPS nonaktif, cookie Secure nonaktif hanya pada mode ini |
| HTTPS | `baseURL` memakai HTTPS, cookie Secure aktif, semua aset/form/export menggunakan URL HTTPS |
| Produksi internet | HTTPS menjadi default; HTTP diarahkan ke HTTPS jika sertifikat dan konfigurasi server sudah siap |

- HTTP tetap didukung untuk instalasi lokal/LAN; HTTP tidak mengenkripsi kredensial maupun transaksi.
- Jangan mengaktifkan pemaksaan HTTPS pada instalasi HTTP-only sehingga menyebabkan redirect loop atau aplikasi tidak dapat diakses.
- Jika di belakang reverse proxy, hanya percaya header forwarded dari proxy yang dikonfigurasi; cegah spoofing host/protokol.
- URL dibuat melalui helper dan konfigurasi canonical base URL; tidak hard-code domain atau skema pada view/JavaScript.
- Cookie menggunakan HttpOnly dan SameSite yang sesuai pada kedua mode. CSRF tetap aktif pada HTTP maupun HTTPS.
- HSTS hanya dikirim pada deployment HTTPS yang siap; tidak dipakai untuk lingkungan HTTP.
- Uji login, sesi, upload, POS, QR ulasan, gambar, dan ekspor PDF pada HTTP serta HTTPS, termasuk deployment proxy yang dipakai.
- URL QR struk mengikuti URL yang dapat dijangkau pelanggan. Jika server hanya ada di LAN, pelanggan harus berada di jaringan tersebut; jangan mengasumsikan alamat localhost dapat diakses pelanggan.

## 9. Model data konseptual

| Entitas | Atribut/relasi utama |
| --- | --- |
| users | identitas, role owner/kasir, password hash, status |
| categories, products, product_variants | kategori, SKU unik, harga, jenis stok, status |
| product_images, toppings | aset, urutan, tambahan harga |
| inventory_items, unit_conversions | bahan/barang, satuan dasar, saldo, biaya rata-rata, stok minimum |
| recipes, recipe_items | varian/topping, bahan, kuantitas, versi |
| suppliers, goods_receipts, goods_receipt_items | pemasok, persetujuan, kuantitas, biaya, pembayaran |
| stock_movements | item, kuantitas bertanda, biaya, jenis, sumber, waktu posting, pelaksana |
| stock_opnames, stock_opname_items | status, cut-off, stok sistem, fisik, selisih, penyetuju |
| shifts, cash_movements | kasir, modal, kas fisik, selisih, perpindahan kas |
| sales, sale_items | nomor, shift, status, snapshot harga/resep/HPP, diskon, pajak, layanan |
| payments | transaksi, metode, nominal alokasi, tunai diterima, kembalian, rekening |
| refunds, refund_items | transaksi asal, item, nilai, keputusan restock, persetujuan |
| expenses, expense_categories | nominal, akun, bukti privat, status, sumber pembayaran |
| accounts, journal_entries, journal_lines | akun, periode, sumber unik, debit/kredit, pembalik |
| accounting_periods | awal/akhir, status tutup, pelaksana |
| banners | gambar, tautan, jadwal, urutan, status |
| review_tokens, reviews, review_images | hash token, expiry, item transaksi, rating, komentar, status moderasi |
| settings, audit_logs | pengaturan dan riwayat perubahan/tindakan |

- Foreign key menjaga relasi; indeks mencakup waktu posting, status, SKU, nomor transaksi, dan sumber jurnal/mutasi.
- Uang memakai desimal presisi tetap; kuantitas bahan minimal presisi 3 desimal pada satuan dasar.
- Waktu disimpan konsisten dan ditampilkan/difilter dalam Asia/Jakarta; konversi batas laporan dilakukan di server.
- Transaksi menyimpan snapshot yang cukup untuk laporan historis walau master berubah.
- Setiap dokumen finansial menyimpan `created_by`, `posted_at`, penyetuju bila ada, dan referensi pembalik.

## 10. Keamanan, keandalan, dan operasional

- Validasi input di server, prepared queries/query builder, escaping output, proteksi CSRF, pembatasan percobaan login, dan pemeriksaan akses per objek.
- Tolak akses customer ke panel, PDF internal, bukti pengeluaran, dan bukti pembayaran.
- Upload memvalidasi ukuran, ekstensi, MIME, dan isi file. Gambar di-decode dan di-encode ulang; nama penyimpanan acak. Tolak SVG, script, executable, dan file yang tidak sesuai tipe.
- Bukti PDF hanya dapat diakses pengguna berhak; gunakan unduhan terkontrol. Jangan mengeksekusi konten upload.
- Audit mencatat perubahan harga/resep, penerimaan, posting opname, refund, pengeluaran, jurnal manual, penutupan periode, moderasi, dan akun. Jangan mencatat password atau token mentah.
- Nomor transaksi, sumber jurnal, dan idempotency key memiliki batasan unik di database.
- Target awal: 5 kasir aktif bersamaan dan 100.000 transaksi historis; spesifikasi server serta dataset uji dicatat pada UAT.
- Target p95: pencarian katalog/POS ≤2 detik dan finalisasi transaksi ≤3 detik pada lingkungan uji yang disepakati.
- PDF rekap tahunan ditargetkan ≤15 detik pada dataset uji. Ekspor detail besar diproses sebagai pekerjaan latar dengan status dan tautan unduhan privat jika melewati batas request.
- Cadangan database dan file upload dilakukan harian; retensi default 30 hari, target kehilangan data maksimal 24 jam dan pemulihan maksimal 4 jam. Uji restore sebelum go-live.
- Log aplikasi menampilkan ID korelasi error tanpa membocorkan kredensial atau data internal kepada pelanggan.
- Riwayat keuangan tidak dihapus melalui CRUD biasa. Kebijakan retensi data dan penghapusan foto ulasan ditentukan owner sebelum operasional.

## 11. Kriteria penerimaan dan skenario UAT

| ID | Skenario | Hasil yang wajib terjadi |
| --- | --- | --- |
| AC-01 | Customer membuka situs | Katalog dan banner dapat dilihat tanpa login; panel ditolak |
| AC-02 | Kasir membuka URL/API akuntansi atau PDF laba | Ditolak server tanpa membocorkan data |
| AC-03 | Owner mengubah harga/resep | Transaksi lama tetap memakai snapshot semula |
| AC-04 | Keranjang Rp50.000, diskon Rp5.000, pajak/layanan mati, tunai Rp50.000 | Total Rp45.000; kembalian Rp5.000; penerimaan kas neto Rp45.000 |
| AC-05 | Pembayaran Rp45.000 dibagi tunai Rp20.000 dan QRIS Rp25.000 | Satu penjualan; dua alokasi pembayaran; laporan tidak menghitung penjualan dua kali |
| AC-06 | Penjualan resep 2 porsi, masing-masing 100 gram tepung | Tepung berkurang 200 gram; HPP dan jurnal tersimpan sekali |
| AC-07 | Tombol bayar diklik ulang atau request dikirim ulang | Mengembalikan hasil transaksi yang sama tanpa duplikasi |
| AC-08 | Dua kasir membeli sisa stok terakhir | Hanya transaksi dengan stok cukup berhasil; saldo tidak negatif |
| AC-09 | Pembuatan jurnal gagal saat pembayaran | Seluruh posting transaksi/pembayaran/stok dibatalkan secara atomik |
| AC-10 | Stok sistem 10 pcs, fisik 8 pcs | Setelah owner menyetujui, mutasi −2 dan jurnal selisih dibuat sekali |
| AC-11 | Kasir mencoba menjual item yang dikunci opname | Pembayaran ditolak dengan alasan stok sedang dihitung |
| AC-12 | Refund martabak yang sudah dibuat | Pendapatan/pembayaran dibalik sesuai nominal; bahan tidak otomatis bertambah |
| AC-13 | Retur minuman layak jual disetujui | Stok kembali dan HPP dibalik dengan biaya transaksi asal |
| AC-14 | Pengeluaran baru disetujui tetapi belum dibayar | Tidak mengurangi kas maupun memposting beban; posting terjadi saat paid |
| AC-15 | Pembelian bahan baku dicatat | Menambah persediaan dan mengurangi kas; tidak menggandakan biaya operasional |
| AC-16 | Laporan harian/bulanan/tahunan diekspor | Filter, detail, dan total PDF sama dengan snapshot panel |
| AC-17 | Refund hari ini untuk penjualan bulan lalu | Refund muncul pada periode hari ini dengan referensi penjualan asal |
| AC-18 | Ulasan dengan token sah dan foto valid dikirim | Tersimpan pending tanpa login, lalu tampil dan dihitung setelah approval |
| AC-19 | Token kedaluwarsa, token sudah digunakan pada item yang sama, atau file palsu | Pengiriman ditolak dengan pesan yang sesuai |
| AC-20 | Banner tidak aktif atau di luar jadwal | Tidak tampil di halaman publik |
| AC-21 | Login/POS/upload/PDF diuji pada HTTP dan HTTPS | Semua berfungsi; HTTPS bebas mixed content; tidak terjadi redirect loop |
| AC-22 | Posting diarahkan ke periode tertutup | Ditolak; koreksi hanya dapat diposting di periode terbuka |
| AC-23 | Kasir menutup shift dengan selisih | Selisih dan alasan tersimpan, memerlukan tinjauan owner |
| AC-24 | Data laporan kosong atau multi-halaman | PDF tetap valid, header/total terbaca, tidak ada kolom terpotong |
| AC-25 | Backup dipulihkan ke lingkungan uji | Saldo stok, jurnal, transaksi, dan lampiran dapat dicocokkan |

## 12. Tahapan implementasi

1. **Fondasi:** konfigurasi CodeIgniter/Tailwind/database, migrasi, akun dan role, layout kuning, dukungan HTTP/HTTPS.
2. **Master dan publik:** produk, varian, resep, bahan/supplier, katalog, banner, dan pengaturan usaha.
3. **Operasional:** penerimaan barang, kartu stok, shift, POS, pembayaran, struk, dan refund.
4. **Kontrol keuangan:** opname, pengeluaran, jurnal otomatis, saldo awal, buku besar, dan tutup periode.
5. **Pelaporan dan ulasan:** laporan PDF semua periode, token ulasan, foto, moderasi, dan dashboard.
6. **Stabilisasi:** UAT, uji konkurensi dan rollback, uji akses, HTTP/HTTPS, backup/restore, serta panduan penggunaan.

Semua kebutuhan FR-01 sampai FR-11 termasuk target rilis pertama. Tahapan di atas mengatur urutan pengembangan, bukan menghapus fitur dari permintaan.

## 13. Definition of Done

- Seluruh kebutuhan fungsional dan AC-01–AC-25 lulus atau memiliki perubahan ruang lingkup yang disepakati secara eksplisit.
- Tiga role bisnis bekerja sesuai matriks akses; customer tetap tanpa login.
- Seluruh posting finansial seimbang, dapat ditelusuri, dan tidak berganda saat retry.
- Stok dan buku pembantu cocok dengan akun persediaan; saldo laci dapat direkonsiliasi per shift.
- PDF harian, bulanan, dan tahunan dapat dibuka/diunduh serta sesuai data dan role.
- Tampilan kuning responsif, halaman panel lengkap, dan upload aman berfungsi.
- Instalasi HTTP dan HTTPS telah diuji dengan konfigurasi terdokumentasi.
- Tersedia source code, migrasi, data awal, contoh konfigurasi tanpa rahasia, instruksi build/deploy, panduan owner/kasir, dan prosedur backup/restore.

## 14. Informasi yang perlu diisi sebelum go-live

Bagian ini tidak menghambat penyusunan desain atau implementasi awal; gunakan default pada dokumen sampai owner menetapkan nilai final.

- Nama usaha, logo, alamat, kontak, jam buka, dan identitas struk/PDF.
- Menu, varian, topping, harga, resep, satuan, supplier, stok awal, serta nilai persediaan.
- Saldo awal kas/bank, modal, aset/kewajiban awal jika ada, dan daftar akun final.
- Batas diskon kasir, aturan refund, pajak/layanan jika berlaku, serta sumber pembayaran non-tunai.
- Perangkat POS, ukuran kertas printer, domain/alamat LAN, sertifikat HTTPS, dan akses pelanggan ke tautan ulasan.
- Petugas moderasi, kebijakan privasi/retensi, jadwal backup, serta penanggung jawab pemulihan.
