# Marwis Sweet Design System

Dokumen ini menjadi acuan visual dan perilaku antarmuka Marwis Sweet. Semua halaman baru harus mengikuti keputusan di sini agar situs publik, panel operasional, dan POS terasa sebagai satu produk.

## 1. Arah Desain

**Reading this as:** public catalog and operational panel for martabak customers, cashiers, and owners, in a contemporary Indonesian street food poster language, dial **ENERGY 2 / RHYTHM 3 / MOTION 2**.

### Konsep: Warung Poster

Marwis Sweet mengambil bahasa visual papan menu dan kemasan martabak Indonesia: kuning yang hangat, judul tebal, susunan harga yang langsung terbaca, dan aksen merah cabai. Eksekusinya dibuat bersih agar tetap cocok untuk transaksi, stok, dan laporan.

Alasan keputusan utama:

- **Kuning sebagai warna utama:** mudah dikenali, hangat, dan sesuai dengan identitas kuliner yang sudah ditetapkan di PRD.
- **Cokelat gelap sebagai warna teks:** memberi rasa hangat sekaligus menghasilkan kontras tinggi di atas kuning.
- **Merah cabai sebagai aksen:** memberi energi pada CTA penting tanpa mengambil peran kuning sebagai identitas utama.
- **Bentuk label poster:** memberi karakter khas tanpa bergantung pada dekorasi generik seperti gradient, glow, atau pola grid.
- **Tata letak rapat dan berirama:** membantu pelanggan memindai menu dan membantu staf menemukan tindakan berikutnya dengan cepat.

### Prinsip

1. **Produk dan tindakan tampil lebih dulu.** Foto, nama menu, harga, status, dan CTA mendapat prioritas tertinggi.
2. **Khas tanpa mengganggu kerja.** Elemen ekspresif dipakai pada judul, label bagian, dan halaman publik. Area transaksi tetap tenang.
3. **Informasi nyata.** Jangan menampilkan statistik, testimoni, stok, promo, atau ulasan palsu.
4. **Setiap keadaan dirancang.** Komponen yang mengambil data wajib memiliki keadaan loading, kosong, berhasil, dan gagal.
5. **Mobile adalah alur utama.** Pengguna harus dapat menelusuri menu dan menyelesaikan pekerjaan penting pada layar 360 px.

## 2. Profil Pengalaman

| Area | Energi | Ritme | Gerak | Karakter |
|---|---:|---:|---:|---|
| Situs publik | 2 | 3 | 2 | Hangat, menggugah selera, mudah dipindai |
| Login | 1 | 2 | 1 | Tenang, ringkas, terpercaya |
| Panel pemilik | 1 | 2 | 1 | Fokus pada keputusan dan pengecualian |
| POS kasir | 2 | 3 | 1 | Cepat, padat, tetap nyaman disentuh |

Gerak tidak boleh menghambat transaksi. Tidak ada animasi berulang, parallax, ticker, atau elemen yang bergerak tanpa tindakan pengguna.

## 3. Identitas Visual

### 3.1 Motif khas

Gunakan satu motif utama bernama **Label Potong**:

- Bidang warna solid dengan salah satu sudut seperti terpotong atau terlipat.
- Dipakai pada label bagian, penanda kategori aktif, dan harga unggulan.
- Dibuat dengan CSS `clip-path` atau pseudo element sederhana.
- Tidak dipakai pada setiap kartu agar hierarki tetap jelas.

Motif pendukung adalah **Garis Struk**, berupa garis tipis putus-putus untuk pemisah subtotal, diskon, pajak, dan total. Motif ini hanya digunakan di konteks transaksi atau ringkasan pesanan.

### 3.2 Logo dan aset

- Gunakan wordmark teks **Marwis Sweet** sampai logo resmi tersedia.
- Jangan membuat logo sementara berbentuk ikon generik.
- Foto produk harus berasal dari foto asli usaha.
- Jika foto belum tersedia, gunakan blok placeholder bertuliskan `Foto produk belum tersedia` dengan rasio yang konsisten.
- Jangan memakai foto stok yang dapat memberi gambaran produk yang keliru.

## 4. Warna

### 4.1 Warna inti

| Token | Nilai | Penggunaan |
|---|---|---|
| `brand.sun` | `#FACC15` | Identitas utama, CTA primer, kategori aktif |
| `brand.cocoa` | `#2D1B12` | Teks utama, navigasi, garis kuat |
| `brand.chili` | `#B93824` | CTA aksen, harga promo, perhatian penting |

### 4.2 Warna netral

| Token | Nilai | Penggunaan |
|---|---|---|
| `surface.cream` | `#FFF8E7` | Latar utama publik |
| `surface.soft-yellow` | `#FEFCE8` | Sorotan lembut dan keadaan kosong |
| `surface.white` | `#FFFFFF` | Form, tabel, panel kerja |
| `ink.muted` | `#6B4D3A` | Teks sekunder |
| `border.warm` | `#E8D9BD` | Garis pemisah dan batas kontrol |
| `surface.disabled` | `#EEE8DC` | Kontrol nonaktif |

### 4.3 Warna status

| Token | Nilai | Makna |
|---|---|---|
| `status.success` | `#166534` | Berhasil, lunas, stok aman |
| `status.warning` | `#92400E` | Perlu perhatian, stok menipis |
| `status.danger` | `#B91C1C` | Gagal, batal, stok habis |
| `status.info` | `#1E40AF` | Informasi proses |

Status tidak boleh disampaikan melalui warna saja. Sertakan ikon dan teks yang jelas, misalnya `Stok menipis` atau `Pembayaran gagal`.

### 4.4 Pasangan kontras yang disetujui

| Teks | Latar | Rasio | Hasil |
|---|---|---:|---|
| `#2D1B12` | `#FACC15` | 10.74:1 | Lulus teks normal |
| `#2D1B12` | `#FFF8E7` | 15.53:1 | Lulus teks normal |
| `#FFFFFF` | `#B93824` | 5.75:1 | Lulus teks normal |
| `#6B4D3A` | `#FFF8E7` | 7.22:1 | Lulus teks normal |
| `#FFFFFF` | `#166534` | 7.13:1 | Lulus teks normal |
| `#FFFFFF` | `#B91C1C` | 6.47:1 | Lulus teks normal |

Kuning selalu memakai teks cokelat gelap. Jangan memakai teks putih di atas kuning.

## 5. Tipografi

### Keluarga font

- **Display:** `Paytone One`, fallback `Arial Black`, `sans-serif`.
- **UI dan isi:** `Plus Jakarta Sans`, fallback `Segoe UI`, `Arial`, `sans-serif`.

Font display hanya digunakan untuk hero, judul bagian publik, dan angka promosi utama. Form, tabel, tombol, navigasi, dan panel selalu menggunakan font UI.

Font harus disimpan secara lokal di aplikasi. Jangan mengandalkan CDN untuk penggunaan produksi.

### Skala tipe

| Token | Mobile | Desktop | Berat | Penggunaan |
|---|---:|---:|---:|---|
| `display-xl` | 40/44 | 64/66 | 400 display | Hero publik |
| `heading-1` | 32/38 | 48/54 | 400 display | Judul halaman publik |
| `heading-2` | 26/32 | 36/42 | 400 display | Judul bagian |
| `heading-ui` | 22/28 | 26/32 | 700 | Judul panel |
| `body-lg` | 18/28 | 18/28 | 500 | Intro dan harga utama |
| `body` | 16/24 | 16/24 | 400 | Teks umum |
| `body-sm` | 14/20 | 14/20 | 500 | Metadata dan tabel |
| `label` | 13/16 | 13/16 | 700 | Label form dan status |

Gunakan kapital penuh hanya untuk label pendek maksimal tiga kata. Hindari paragraf panjang dalam huruf kapital.

## 6. Spasi, Bentuk, dan Kedalaman

### Spasi

Gunakan skala `4, 8, 12, 16, 24, 32, 48, 64, 96` px.

- Jarak antar elemen di dalam komponen: 8 sampai 16 px.
- Jarak antar kelompok: 24 sampai 32 px.
- Jarak antar bagian halaman publik: 64 px pada mobile dan 96 px pada desktop.
- Lebar konten utama: maksimal 1200 px dengan gutter 16 px mobile, 24 px tablet, dan 32 px desktop.

### Radius

| Token | Nilai | Penggunaan |
|---|---:|---|
| `radius-sm` | 4 px | Input kecil, label, tabel |
| `radius-md` | 10 px | Tombol, input, kartu operasional |
| `radius-lg` | 18 px | Kartu produk dan panel utama |
| `radius-xl` | 28 px | Hero atau komposisi merek tertentu |

Pill hanya untuk status, filter pilihan tunggal, dan chip kategori. Tombol biasa tidak berbentuk pill.

### Bayangan

- Kartu biasa memakai border, bukan bayangan.
- Bayangan dipakai untuk modal, popover, drawer, dan keranjang POS yang mengambang.
- Jangan memakai glow.

## 7. Komponen

### Tombol

**Primer**

- Latar `brand.sun`, teks `brand.cocoa`, border cokelat 1 px.
- Hover menggelapkan kuning menjadi `#EAB308` dan menggeser elemen maksimal 1 px.
- Contoh label: `Lihat menu`, `Simpan produk`, `Bayar sekarang`.

**Aksen**

- Latar `brand.chili`, teks putih.
- Hanya untuk tindakan yang perlu perhatian lebih, misalnya `Tambahkan pesanan`.

**Sekunder**

- Latar transparan atau putih, teks cokelat, border `border.warm`.

**Destruktif**

- Latar putih, teks dan border `status.danger`.
- Konfirmasi harus menyebut objek dan dampaknya.

Semua tombol memiliki tinggi minimal 44 px dan fokus keyboard yang terlihat.

### Input

- Label selalu berada di atas kontrol.
- Placeholder hanya memberi contoh, bukan menggantikan label.
- Tinggi minimal 44 px.
- Pesan kesalahan muncul di bawah kolom dan menjelaskan perbaikan yang diperlukan.
- Fokus memakai outline 3 px `brand.sun` dengan offset 2 px dan border `brand.cocoa`.

### Kartu produk

Kartu berisi foto atau placeholder, nama, varian singkat, harga, status ketersediaan, dan satu tindakan utama. Seluruh kartu tidak menjadi tombol jika di dalamnya sudah terdapat tombol.

### Tabel operasional

- Angka keuangan rata kanan dan memakai angka tabular.
- Kolom nama atau deskripsi rata kiri.
- Header tetap terlihat bila tabel panjang.
- Pada mobile, baris berubah menjadi blok data berlabel. Jangan memaksa scroll horizontal untuk tugas utama.
- Aksi baris diletakkan dalam menu atau kelompok tombol yang dapat digunakan dengan keyboard.

### Status

Gunakan ikon, label teks, dan warna. Contoh:

- `✓ Lunas`
- `! Stok menipis`
- `× Dibatalkan`

### Dialog konfirmasi

Wajib digunakan untuk posting opname, refund, penutupan periode, dan pembatalan final. Judul harus menyebut tindakannya dan isi harus menjelaskan akibatnya.

## 8. Arsitektur Halaman

### 8.1 Situs publik

Urutan halaman beranda:

1. Header dengan wordmark, tautan `Menu`, `Tentang`, `Kontak`, dan tombol `Masuk panel`.
2. Hero dengan pesan produk yang spesifik, CTA `Lihat menu`, serta foto produk asli atau placeholder.
3. Daftar kategori yang dapat difilter.
4. Daftar menu dengan harga dan ketersediaan nyata.
5. Informasi toko seperti alamat, jam buka, dan kontak yang berasal dari konfigurasi.
6. Footer ringkas.

Hindari penghitung pelanggan, testimoni, logo mitra, atau klaim keunggulan jika datanya belum tersedia.

### 8.2 Login

- Form menjadi fokus utama dan maksimal selebar 440 px.
- Wordmark serta satu kalimat singkat berada di atas form.
- Jangan menambahkan ilustrasi dekoratif yang tidak membantu proses masuk.
- Kesalahan kredensial muncul sebagai pesan ringkas dan tidak mengungkap akun mana yang valid.

### 8.3 Panel pemilik

Panel dibuka dengan informasi yang membutuhkan tindakan, lalu ringkasan data nyata.

Urutan desktop:

1. Sidebar navigasi.
2. Topbar berisi judul konteks, pengguna aktif, dan aksi yang relevan.
3. Breadcrumb bila kedalaman halaman lebih dari satu tingkat.
4. Peringatan operasional, misalnya stok menipis atau periode belum ditutup.
5. Konten utama berupa tabel, formulir, atau ringkasan.

Dashboard tidak boleh menjadi kumpulan empat kartu statistik generik. Tampilkan data hanya jika membantu pemilik membuat keputusan.

### 8.4 POS kasir

Desktop memakai dua area:

- Area produk fleksibel di kiri.
- Keranjang selebar 360 sampai 420 px di kanan dan tetap terlihat selama transaksi.

Mobile memakai satu alur:

- Produk menjadi layar utama.
- Ringkasan keranjang melekat di bawah.
- Keranjang lengkap dibuka sebagai bottom sheet.
- Tindakan bayar tetap mudah dijangkau tanpa menutup daftar produk.

## 9. Responsif

| Rentang | Perilaku |
|---|---|
| `< 640 px` | Satu kolom, gutter 16 px, drawer navigasi, bottom sheet untuk keranjang |
| `640 sampai 899 px` | Grid produk dua kolom, gutter 24 px, sidebar tetap menjadi drawer |
| `900 sampai 1199 px` | Sidebar panel dapat dipadatkan, layout dua area bila ruang cukup |
| `>= 1200 px` | Konten maksimal 1200 px, sidebar penuh, POS dua area |

Titik uji wajib: 360, 768, 1024, dan 1440 px. Tidak boleh ada teks terpotong, kontrol keluar layar, atau informasi penting yang hanya tersedia saat hover.

## 10. Interaksi dan Gerak

### Durasi

- Umpan balik kontrol: 120 sampai 160 ms.
- Drawer, modal, dan bottom sheet: 200 sampai 240 ms.
- Pergantian daftar setelah filter: maksimal 200 ms.

### Kurva

Gunakan `cubic-bezier(0.2, 0.8, 0.2, 1)` untuk masuk dan `ease-in` untuk keluar.

### Aturan

- Animasi hanya untuk menjelaskan perubahan keadaan atau hubungan antarelemen.
- Hormati `prefers-reduced-motion` dengan menghapus transform dan memperpendek durasi.
- Hover tidak boleh menjadi satu-satunya cara menemukan aksi.
- Setelah dialog tertutup, fokus kembali ke elemen yang membukanya.
- Drawer dan modal dapat ditutup dengan `Escape`.

## 11. Keadaan Sistem

Setiap area yang mengambil data wajib memiliki:

### Loading

Gunakan skeleton yang mengikuti bentuk konten atau label `Memuat data...`. Jangan memakai spinner tanpa konteks untuk proses lebih dari satu detik.

### Kosong

Jelaskan keadaan dan tindakan berikutnya. Contoh: `Belum ada produk. Tambahkan produk pertama untuk mulai menyusun menu.`

### Gagal

Sebutkan apa yang gagal dan sediakan aksi yang relevan. Contoh: `Daftar produk belum dapat dimuat. Coba lagi.`

### Berhasil

Berikan umpan balik dekat dengan tindakan. Toast tidak boleh menjadi satu-satunya bukti bahwa data penting telah tersimpan.

### Nonaktif

Kontrol nonaktif harus tetap terbaca dan, bila alasannya tidak jelas, sertakan penjelasan singkat.

## 12. Aksesibilitas

- Target sentuh minimum 44 x 44 px.
- Urutan fokus mengikuti urutan visual.
- Semua kontrol dapat digunakan dengan keyboard.
- Fokus selalu terlihat dan tidak tertutup elemen lengket.
- Gunakan elemen HTML semantik sebelum menambahkan peran ARIA.
- Ikon dekoratif memakai `aria-hidden="true"`.
- Tombol ikon memiliki nama yang dapat dibaca pembaca layar.
- Pesan kesalahan form terhubung ke input dengan `aria-describedby`.
- Judul halaman memakai satu `h1`, lalu hierarki judul berurutan.
- Harga dan jumlah tidak disampaikan melalui posisi atau warna saja.

## 13. Suara dan Isi

Tulisan harus singkat, konkret, dan sesuai konteks usaha.

**Gunakan:**

- `Lihat menu hari ini`
- `Tambah ke keranjang`
- `Stok tersisa 4`
- `Simpan perubahan`
- `Pesanan belum dibayar`

**Hindari:**

- `Jelajahi pengalaman rasa terbaik`
- `Solusi kuliner terdepan`
- `Klik di sini`
- `Submit`
- Klaim seperti `Nomor 1` tanpa bukti

Gunakan Bahasa Indonesia sebagai bahasa utama. Nama teknis hanya muncul jika memang dipahami oleh pengguna yang dituju.

## 14. Token CSS Awal

```css
@theme {
  --color-brand-sun: #facc15;
  --color-brand-sun-hover: #eab308;
  --color-brand-cocoa: #2d1b12;
  --color-brand-chili: #b93824;

  --color-surface-cream: #fff8e7;
  --color-surface-soft-yellow: #fefce8;
  --color-surface-white: #ffffff;
  --color-ink-muted: #6b4d3a;
  --color-border-warm: #e8d9bd;

  --color-status-success: #166534;
  --color-status-warning: #92400e;
  --color-status-danger: #b91c1c;
  --color-status-info: #1e40af;

  --font-display: "Paytone One", "Arial Black", sans-serif;
  --font-sans: "Plus Jakarta Sans", "Segoe UI", Arial, sans-serif;

  --radius-sm: 0.25rem;
  --radius-md: 0.625rem;
  --radius-lg: 1.125rem;
  --radius-xl: 1.75rem;
}
```

Token ini menjadi sumber utama. Jangan menambahkan warna bebas di komponen jika maknanya sudah tersedia dalam token.

## 15. Pemeriksaan Sebelum Rilis

- [ ] Halaman mengikuti konsep Warung Poster dan tidak berubah menjadi template dashboard generik.
- [ ] Kuning memakai teks cokelat gelap.
- [ ] Warna aksen digunakan hemat dan memiliki fungsi jelas.
- [ ] Semua data contoh ditandai sebagai contoh atau diganti data nyata.
- [ ] Semua tautan dan tombol bekerja.
- [ ] Loading, kosong, berhasil, gagal, dan nonaktif tersedia.
- [ ] Navigasi keyboard dan fokus terlihat bekerja.
- [ ] Modal dan drawer mengelola fokus dengan benar.
- [ ] Tata letak diperiksa pada 360, 768, 1024, dan 1440 px.
- [ ] `prefers-reduced-motion` dihormati.
- [ ] Foto yang tampil adalah aset asli atau placeholder yang jujur.
- [ ] Tidak ada horizontal scroll pada alur utama mobile.

## 16. Rujukan Arah Visual

- Psikologi warna restoran digunakan sebagai bahan pertimbangan untuk kehangatan kuning serta energi merah: [Wasserstrom, Color Psychology for Restaurant Design](https://www.wasserstrom.com/blog/2022/12/07/color-psychology-for-restaurant-design/).
- Penggunaan warna dan tipografi pada identitas martabak Indonesia dipakai sebagai konteks kategori, bukan sebagai pola untuk disalin: [Katalog Imago 9, UMN](https://dkv.umn.ac.id/wp-content/uploads/2025/09/Katalog-Imago-9.pdf).
- Prinsip latar netral hangat digunakan agar foto makanan dan informasi harga tetap menjadi fokus: [VisibleMenus, Restaurant Menu Colors](https://visiblemenus.com/insights/restaurant-menu-colors/).

