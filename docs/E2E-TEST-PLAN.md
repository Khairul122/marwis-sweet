# Rencana E2E Test — Marwis Sweet

Dokumen ini adalah instruksi kerja untuk AI agent yang akan membangun dan menjalankan E2E test. Baca seluruh dokumen sebelum menulis kode. Acuan bisnis: [PRD](../PRD%20(1).md) §5 (FR-01–FR-11) dan §11 (AC-01–AC-25). Acuan deployment: [DEPLOYMENT-UAT.md](DEPLOYMENT-UAT.md).

## 1. Ringkasan aplikasi

| Aspek | Nilai |
| --- | --- |
| Stack | CodeIgniter 4.7 (PHP 8.2+), MySQL/MariaDB, Tailwind 4, Dompdf, render server-side (bukan SPA) |
| Base URL lokal | `http://localhost:8080/` (`php spark serve`) |
| Role | `owner`, `cashier` (login di `/login`), customer tamu (tanpa login) |
| Proteksi | CSRF global berbasis cookie, throttle login 5 percobaan/15 menit per identitas+IP |
| Filter route | `auth`, `owner`, `cashier`, `inventorystaff` (owner+kasir) — lihat `app/Config/Routes.php` |
| Penolakan akses | Filter role me-redirect ke `/panel` dengan flash error, bukan 403 |
| Test yang sudah ada | PHPUnit feature test di `tests/feature/` (memakai `FeatureTestTrait`, CSRF dimatikan). E2E browser belum ada |

Modul dan route utama:

| Modul | Route | Role |
| --- | --- | --- |
| Publik: beranda, detail produk | `/`, `/menu/{slug}` | Tamu |
| Ulasan tamu | `/ulasan/{token}` (GET/POST), `/ulasan/foto/{id}` | Tamu dengan token |
| Dashboard | `/panel` | Owner, kasir |
| Katalog (kategori, produk, varian, gambar) | `/panel/catalog` | Owner |
| Master (supplier, bahan, konversi, resep, topping) | `/panel/master` | Owner |
| Pengaturan usaha, kebijakan diskon, banner | `/panel/settings` | Owner |
| Inventori, penerimaan barang, penyesuaian stok | `/panel/inventory`, `/receipts/*`, `/adjustments/*` | Owner, kasir (approve/reject/reverse: owner) |
| Stok opname | `/panel/opnames` | Owner, kasir (approve/reject: owner) |
| Pengeluaran | `/panel/expenses` | Kasir ajukan; owner approve/pay/reject |
| Akuntansi (jurnal manual, saldo awal, tutup/buka periode) | `/panel/accounting` | Owner |
| Laporan + PDF | `/panel/reports`, `/sales.pdf`, `/{type}.pdf` | Owner, kasir (konten dibatasi) |
| Shift kasir | `/panel/shifts` (open, cash-movements, close) | Kasir |
| Review shift | `/panel/shift-reviews` | Owner |
| POS | `/panel/pos` (orders, cancel, finalize) | Kasir |
| Transaksi, struk, struk PDF, refund, tautan ulasan | `/panel/transactions/*` | Owner, kasir (approve refund: owner) |
| Pengguna | `/panel/users` | Owner |
| Moderasi ulasan | `/panel/reviews` | Owner |
| Audit | `/panel/audit` | Owner |

## 2. Keputusan teknis

- **Tool:** Playwright Test + TypeScript. Playwright sudah tersedia lewat `npx` (v1.64.x) dan Node v24 terpasang.
- **Lokasi:** `e2e/` di root repo, config `playwright.config.ts` di root. Tambahkan `@playwright/test` sebagai devDependency di `package.json` yang sudah ada, plus script `"test:e2e": "playwright test"`. Jangan membuat `package.json` baru.
- **Browser:** Chromium wajib. Tambah project `mobile` (viewport 390×844) hanya untuk spec yang ditandai `@mobile` (publik, POS, form ulasan).
- **Eksekusi:** `workers: 1` dan `fullyParallel: false` sebagai default karena seluruh spec berbagi satu database dan saldo stok. Pengecualian: AC-08 (konkurensi) memakai dua browser context di dalam satu test.
- **CSRF tetap aktif.** E2E harus lewat form asli. Jangan menonaktifkan filter `csrf` seperti di PHPUnit.
- **Verifikasi database** dilakukan lewat helper Node (`mysql2`, devDependency) yang hanya membaca schema E2E. Mutasi data tetap melalui UI, kecuali fixture yang memang tidak punya UI (lihat §4.3).

## 3. Lingkungan uji

### 3.1 Database terisolasi

Wajib memakai schema terpisah. Jangan pernah menjalankan E2E terhadap `marwis_sweet`.

```sql
CREATE DATABASE marwis_sweet_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3.2 Server E2E

Jalankan instance kedua di port 8081 dengan env override, supaya `.env` milik developer tidak berubah. Config `webServer` Playwright menjalankan:

```text
php spark serve --port 8081
```

dengan environment variable:

```text
CI_ENVIRONMENT=testing
app.baseURL=http://localhost:8081/
database.default.database=marwis_sweet_e2e
```

Jika env var dengan titik tidak terbaca di shell Windows, buat skrip `e2e/scripts/serve.mjs` yang men-spawn `php spark serve` dengan `env` berisi kunci di atas. Verifikasi sekali bahwa server benar-benar memakai `marwis_sweet_e2e` (misalnya buat user lewat UI lalu cek tabel `users` di schema E2E).

`CI_ENVIRONMENT=testing` diperlukan karena `DevelopmentAccountSeeder` dan `EvaluationDataSeeder` menolak production. Pastikan halaman error/toolbar debug tidak mengganggu selector; bila mengganggu, set `CI_ENVIRONMENT=development` dan catat alasannya.

### 3.3 Reset data (`globalSetup`)

Urutan di `e2e/global-setup.ts`, memakai env yang sama dengan server:

1. `php spark migrate:refresh --all -f`
2. `php spark db:seed DevelopmentSeeder` → akun `owner` / `DemoOwner!2026` dan `kasir` / `DemoKasir!2026`, plus data `demo-`/`DEMO-`.
3. Hapus cache throttle login (`writable/cache/*` kecuali `index.html`) agar test login gagal tidak mengunci akun.
4. Login sebagai owner dan kasir, simpan `storageState` ke `e2e/.auth/owner.json` dan `e2e/.auth/cashier.json` (masukkan `e2e/.auth/` ke `.gitignore`).

Data yang dibutuhkan spec tertentu (bahan dengan stok 10 pcs, produk resep 100 g tepung, minuman `finished_good`, dsb.) dibuat oleh spec itu sendiri lewat UI owner dengan nama berawalan `E2E-<timestamp>` agar tidak bentrok dan mudah dilacak.

## 4. Konvensi kode

### 4.1 Struktur

```text
e2e/
  global-setup.ts
  fixtures.ts          # test.extend: ownerPage, cashierPage, guestPage, db
  helpers/
    db.ts              # query read-only ke marwis_sweet_e2e
    money.ts           # parse "Rp45.000" -> 45000
    flash.ts           # baca flash success/error
    pdf.ts             # unduh PDF, cek header %PDF, ekstrak teks
    data.ts            # builder data via UI: bahan, produk resep, finished_good, penerimaan
  specs/
    01-auth-access.spec.ts
    02-public-catalog.spec.ts
    03-catalog-master.spec.ts
    04-inventory.spec.ts
    05-pos-checkout.spec.ts
    06-pos-concurrency.spec.ts
    07-refund.spec.ts
    08-opname.spec.ts
    09-expenses.spec.ts
    10-accounting.spec.ts
    11-shift.spec.ts
    12-reports-pdf.spec.ts
    13-reviews.spec.ts
    14-banners.spec.ts
    15-users-audit.spec.ts
    16-security-upload.spec.ts
    17-mobile.spec.ts
```

### 4.2 Selector

Urutan prioritas: `getByRole` / `getByLabel` (form punya `<label for>` dalam Bahasa Indonesia) → atribut `data-*` yang sudah ada (contoh POS: `[data-add-product][data-variant-id="…"]`, `[data-topping]`, `[data-order-type]`, `[data-customer-name]`, `[data-discount-approval]`) → `getByText`. Hindari selector kelas Tailwind.

Bila elemen tidak bisa dipilih secara stabil, boleh menambahkan `data-testid` di view terkait. Satu commit terpisah, perubahan minimal, tanpa mengubah tampilan.

### 4.3 Aturan assertion

- Setiap test minimal punya satu assertion UI dan, untuk alur yang menyentuh uang/stok/jurnal, satu assertion database.
- Uang dibandingkan sebagai integer Rupiah, bukan float.
- Untuk jurnal: setiap `journal_entries` berstatus posted harus seimbang (`SUM(debit) = SUM(credit)` di `journal_lines`). Buat helper `assertJournalsBalanced()` dan panggil di `afterAll` setiap spec finansial.
- Penolakan role: assert URL akhir `/panel` + flash error, dan assert data sensitif (HPP, laba, nominal jurnal) tidak ada di HTML respons.
- Fixture tanpa UI (contoh: token ulasan kedaluwarsa, transaksi bulan lalu untuk AC-17) boleh di-update lewat `db.ts`. Tandai dengan komentar `// fixture: tidak ada UI untuk ini` dan batasi ke kolom tanggal/status saja.
- Jangan pakai `waitForTimeout`. Tunggu navigasi, respons, atau elemen.
- Nama test memuat ID AC/FR, contoh `test('AC-04 diskon Rp5.000, tunai Rp50.000 → kembalian Rp5.000', …)`.

## 5. Skenario per spec

Prioritas: **P0** wajib sebelum rilis, **P1** penting, **P2** bila waktu ada. Kerjakan berurutan per fase di §6.

### 01 — Autentikasi dan akses (FR-01, AC-01, AC-02)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| AUTH-01 | P0 | Login owner via username, lalu via email | Masuk `/panel`, sidebar owner (Akuntansi, Pengguna, Audit tampil) |
| AUTH-02 | P0 | Login kasir | Sidebar kasir tanpa menu Akuntansi/Pengguna/Audit/Katalog |
| AUTH-03 | P0 | Password salah | Tetap di `/login`, pesan error, tanpa bocor info apakah username ada |
| AUTH-04 | P1 | 6× password salah | Percobaan ke-6 ditolak walau password benar (throttle). Bersihkan cache setelahnya |
| AUTH-05 | P0 | Logout lalu tekan Back / buka `/panel` | Diarahkan ke `/login` |
| AUTH-06 | P0 | AC-01: tamu membuka `/panel`, `/panel/pos`, `/panel/reports/sales.pdf` | Redirect ke `/login`, tanpa konten panel |
| AUTH-07 | P0 | AC-02: kasir membuka setiap route grup `owner` (accounting, users, audit, catalog, master, settings, shift-reviews, reviews) via URL langsung | Redirect `/panel` + flash error, HTML tanpa angka HPP/laba |
| AUTH-08 | P0 | AC-02: kasir POST ke `/panel/accounting/journals`, `/panel/refunds/{id}/approve`, `/panel/expenses/{id}/pay` dengan CSRF token sah | Ditolak, tidak ada baris baru di DB |
| AUTH-09 | P0 | Kasir mengunduh PDF laporan | PDF tanpa kolom/teks HPP, laba, buku besar |
| AUTH-10 | P1 | Owner membuka `/panel/pos` dan `/panel/shifts` | Ditolak (POS khusus kasir) |
| AUTH-11 | P1 | Owner menonaktifkan kasir saat kasir sedang login di context lain | Request berikutnya kasir keluar ke `/login` |
| AUTH-12 | P1 | `returnto`: tamu membuka `/panel/reports`, login | Kembali ke `/panel/reports` |

### 02 — Katalog publik (FR-02)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| PUB-01 | P0 | Beranda | Nama usaha, kategori, produk, jam buka, alamat, kontak, harga format `Rp` |
| PUB-02 | P0 | Detail produk `/menu/{slug}` | Foto, varian, harga, rating rata-rata + jumlah ulasan approved |
| PUB-03 | P0 | Owner menonaktifkan produk | Hilang dari beranda, `/menu/{slug}` tidak menampilkan produk, tidak ada di grid POS |
| PUB-04 | P1 | Pencarian dan filter kategori/ketersediaan | Hasil sesuai |
| PUB-05 | P0 | HTML publik | Tidak memuat stok angka, HPP, atau biaya; tidak ada tombol checkout |
| PUB-06 | P1 | Penanda demo aktif setelah seed; owner menyimpan identitas usaha | Notifikasi demo hilang |

### 03 — Katalog dan master (FR-03, AC-03)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| CAT-01 | P0 | Owner membuat kategori, produk resep, varian, resep (tepung 100 g), topping | Muncul di panel, publik, dan POS |
| CAT-02 | P0 | Buat bahan dengan satuan dasar + konversi 1 kg = 1.000 g | Konversi tersimpan |
| CAT-03 | P0 | AC-03: jual produk, lalu owner ubah harga dan resep, jual lagi | Struk/`sale_items` transaksi lama tetap harga & HPP lama |
| CAT-04 | P1 | Hapus produk yang sudah dipakai transaksi | Ditolak; hanya bisa dinonaktifkan |
| CAT-05 | P1 | Upload foto ke-6 | Ditolak (maks 5) |
| CAT-06 | P2 | Ubah urutan gambar, edit varian | Urutan di publik berubah |

### 04 — Inventori (FR-04, AC-15)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| INV-01 | P0 | Owner membuat penerimaan barang 1 kg tepung @Rp12.000, posting | Stok +1.000 g, nilai persediaan +12.000, jurnal Persediaan/Kas |
| INV-02 | P0 | AC-15: setelah INV-01 | Tidak ada baris beban operasional; total pengeluaran tidak berubah |
| INV-03 | P0 | Kasir membuat penerimaan → submit → owner approve → posting | Status mengikuti alur; stok bertambah hanya setelah posting |
| INV-04 | P1 | Owner menolak penerimaan kasir | Stok tidak berubah, alasan tersimpan |
| INV-05 | P0 | Penyesuaian stok: kasir ajukan → owner approve → posting → owner reverse | Mutasi pembalik dibuat, mutasi asli tidak diubah |
| INV-06 | P1 | Kartu stok | Setiap mutasi punya tipe, waktu, pelaksana, jumlah, alasan |
| INV-07 | P1 | Penyesuaian yang membuat stok negatif | Ditolak |

### 05 — POS checkout (FR-06, AC-04–AC-07, AC-09, AC-11)

Prasyarat: kasir punya shift terbuka (buat di `beforeAll` lewat `/panel/shifts`).

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| POS-01 | P0 | AC-04: keranjang Rp50.000, diskon Rp5.000, tunai Rp50.000 | Total Rp45.000, kembalian Rp5.000; DB: 1 `sales`, pembayaran kas neto 45.000 |
| POS-02 | P0 | AC-05: total Rp45.000, tunai Rp20.000 + QRIS Rp25.000 | 1 penjualan, 2 alokasi pembayaran, label "dikonfirmasi manual"; laporan jumlah transaksi = 1 |
| POS-03 | P0 | AC-06: 2 porsi produk resep @100 g tepung | Tepung −200 g; tepat 1 jurnal penjualan & 1 jurnal HPP untuk sale tersebut |
| POS-04 | P0 | AC-07: klik "Bayar" dua kali cepat, lalu kirim ulang POST `finalize` dengan `idempotency_key` sama (`page.request` + cookie & CSRF dari halaman) | Respons menunjuk transaksi yang sama; `COUNT(sales)` naik 1 |
| POS-05 | P0 | AC-11: buat opname mencakup tepung (status counting), kasir bayar produk resep | Ditolak dengan pesan "Stok … sedang dikunci oleh opname …"; tidak ada `sales` baru |
| POS-06 | P1 | Diskon melebihi batas kasir | Panel `[data-discount-approval]` muncul; tanpa kredensial owner ditolak; dengan kredensial owner sah berhasil |
| POS-07 | P1 | Tahan keranjang → lanjutkan → bayar | Order held lalu paid; stok dicek ulang saat bayar |
| POS-08 | P1 | Batalkan keranjang held dengan alasan | Status cancelled, stok & jurnal tidak berubah |
| POS-09 | P1 | Topping + catatan "tanpa kacang", dine-in nomor meja | Tercetak di struk |
| POS-10 | P1 | Pembayaran kurang dari total | Ditolak |
| POS-11 | P0 | Struk dan struk PDF | Memuat identitas usaha, nomor, kasir, item, diskon, pembayaran, kembalian, tautan ulasan; PDF diawali `%PDF` |
| POS-12 | P2 | AC-09: kegagalan jurnal di tengah finalisasi | Tidak bisa dipicu dari UI tanpa fault injection. Catat sebagai cakupan PHPUnit; jangan menambah hook produksi untuk E2E |

### 06 — Konkurensi (AC-08)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| CON-01 | P0 | Buat kasir kedua via `/panel/users`. Barang `finished_good` stok 1. Dua context kasir (masing-masing shift sendiri) menyiapkan keranjang, lalu `Promise.all` klik Bayar | Tepat satu berhasil, satu ditolak stok tidak cukup; `quantity_on_hand` = 0, tidak negatif |
| CON-02 | P1 | Satu kasir mencoba membuka shift kedua | Ditolak (satu shift aktif per kasir) |

### 07 — Refund (AC-12, AC-13, FR-11 refund penuh)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| REF-01 | P0 | AC-12: kasir ajukan refund martabak, owner approve | Penjualan neto & pembayaran dibalik sesuai nominal; stok tepung tidak bertambah |
| REF-02 | P0 | AC-13: refund minuman layak jual, owner approve | Stok kembali; jurnal Persediaan/HPP sebesar biaya transaksi asal |
| REF-03 | P0 | Refund kumulatif melebihi kuantitas/nilai | Ditolak |
| REF-04 | P1 | Owner reject refund | Status ditolak, tidak ada jurnal |
| REF-05 | P1 | Refund penuh | Status `refunded`; token ulasan yang belum dipakai nonaktif |
| REF-06 | P1 | Refund sebagian | Status `partially_refunded` |

### 08 — Stok opname (FR-05, AC-10)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| OPN-01 | P0 | AC-10: item stok 10 pcs, kasir hitung fisik 8, submit; owner approve | Mutasi −2 dan satu jurnal selisih; approve ulang tidak menggandakan |
| OPN-02 | P0 | Owner reject → kasir koreksi → submit ulang | Status kembali ke alur |
| OPN-03 | P1 | Cancel sesi dengan alasan | Kunci terbuka, saldo tidak berubah, POS bisa menjual lagi |
| OPN-04 | P1 | Upload bukti, buka `/opnames/{id}/evidence` sebagai tamu | Tamu ditolak; owner bisa unduh |
| OPN-05 | P2 | Usulan opname: refresh, start, dismiss | Sesi dibuat dari usulan terpilih |
| OPN-06 | P1 | Sesi approved | Tidak bisa diedit |

### 09 — Pengeluaran (FR-07, AC-14)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| EXP-01 | P0 | AC-14: kasir ajukan, owner approve | Belum ada jurnal, saldo kas tetap |
| EXP-02 | P0 | Owner pay | Jurnal Beban/Kas terbentuk sekali, saldo kas berkurang |
| EXP-03 | P1 | Sumber laci kasir | Wajib shift aktif; masuk perhitungan kas seharusnya |
| EXP-04 | P1 | Nominal ≤ 0, bukti > 5 MB | Ditolak |
| EXP-05 | P1 | Kasir hanya melihat pengajuan sendiri | Pengajuan kasir lain tidak tampil |
| EXP-06 | P2 | Owner kelola kategori | Kategori nonaktif tidak bisa dipilih |

### 10 — Akuntansi (FR-08, AC-22)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| ACC-01 | P0 | Jurnal manual tidak seimbang | Ditolak |
| ACC-02 | P0 | Jurnal manual seimbang dengan alasan | Tersimpan, tercatat di audit |
| ACC-03 | P0 | AC-22: tutup periode bulan lalu, coba posting ke tanggal di periode itu | Ditolak |
| ACC-04 | P1 | Buka kembali periode dengan alasan | Berhasil, tercatat di audit |
| ACC-05 | P1 | Saldo awal | Harus seimbang |
| ACC-06 | P0 | Setelah seluruh spec finansial | `assertJournalsBalanced()` lulus; saldo akun persediaan = `SUM(inventory_items.inventory_value)` |

### 11 — Shift (FR-06, AC-23)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| SHF-01 | P0 | Buka shift modal Rp200.000, jual tunai Rp45.000, kas keluar Rp10.000 | Kas seharusnya Rp235.000 |
| SHF-02 | P0 | AC-23: tutup shift dengan kas fisik Rp230.000 + alasan | Selisih −Rp5.000 dan alasan tersimpan; muncul di `/panel/shift-reviews` |
| SHF-03 | P0 | Owner approve/reject review shift | Jurnal selisih kas hanya setelah approve |
| SHF-04 | P0 | Setelah shift ditutup, kasir membuka POS | Tidak bisa finalize tanpa shift baru |

### 12 — Laporan dan PDF (FR-09, AC-16, AC-17, AC-24)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| RPT-01 | P0 | AC-16: periode harian, bulanan, tahunan | Total panel = total di teks PDF (`helpers/pdf.ts`) |
| RPT-02 | P0 | AC-17: penjualan bertanggal bulan lalu (fixture tanggal via DB), refund hari ini | Refund muncul di laporan hari ini dengan nomor transaksi asal |
| RPT-03 | P0 | AC-24: periode kosong | Panel & PDF "Tidak ada data pada periode ini", total nol, PDF valid |
| RPT-04 | P1 | AC-24: data multi-halaman (`EvaluationDataSeeder` di run terpisah) | PDF > 1 halaman, nomor halaman, header tabel berulang |
| RPT-05 | P1 | Filter metode pembayaran pada transaksi gabungan (POS-02) | Jumlah transaksi tidak ganda |
| RPT-06 | P1 | Pagination transaksi/refund | Navigasi halaman, total tetap total keseluruhan |
| RPT-07 | P1 | PDF operasional `/panel/reports/{type}.pdf` untuk setiap `type` yang dirender di view laporan | Owner dapat semua; kasir hanya yang diizinkan |
| RPT-08 | P2 | Dashboard owner | Penjualan bersih, HPP, laba kotor konsisten dengan laporan hari ini |

### 13 — Ulasan tamu (FR-11, AC-18, AC-19)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| REV-01 | P0 | AC-18: ambil tautan ulasan dari struk, tamu (context tanpa cookie) kirim rating 5, komentar 10+ karakter, 1 foto JPEG | Status pending, tidak tampil di publik |
| REV-02 | P0 | Owner approve di `/panel/reviews` | Tampil di `/menu/{slug}`, rata-rata & jumlah ulasan bertambah |
| REV-03 | P0 | AC-19: kirim ulang ke baris produk yang sama | Ditolak |
| REV-04 | P0 | AC-19: token kedaluwarsa (fixture `expires_at` via DB) | Halaman "tidak tersedia" |
| REV-05 | P0 | AC-19: file `.jpg` berisi teks/PHP, SVG, > 2 MB | Ditolak |
| REV-06 | P1 | Komentar < 10 atau > 1.000 karakter, rating 0/6 | Ditolak |
| REV-07 | P1 | Token acak/tidak ada | Halaman tidak tersedia, tanpa bocor data transaksi |
| REV-08 | P1 | Owner sembunyikan ulasan approved dengan alasan | Hilang dari publik dan rata-rata |
| REV-09 | P2 | Foto ulasan yang diunggah | Metadata EXIF GPS terhapus (unduh dari `/ulasan/foto/{id}`, cek dengan parser EXIF) |

### 14 — Banner (FR-10, AC-20)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| BAN-01 | P0 | Banner aktif dalam jadwal | Tampil di beranda dengan alt text |
| BAN-02 | P0 | AC-20: banner nonaktif, dan banner dengan jadwal lewat/akan datang | Tidak tampil |
| BAN-03 | P0 | Tautan `javascript:alert(1)` | Ditolak |
| BAN-04 | P1 | Tidak ada banner aktif | Hero bawaan dengan nama usaha |
| BAN-05 | P1 | Carousel dapat dioperasikan keyboard | Tombol kontrol bisa difokus dan ditekan Enter |

### 15 — Pengguna dan audit

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| USR-01 | P0 | Owner buat kasir, reset password, nonaktifkan | Kasir login dengan password baru; setelah nonaktif login ditolak |
| USR-02 | P1 | Username/email duplikat | Ditolak |
| AUD-01 | P1 | Setelah perubahan harga, posting opname, refund, pengeluaran, jurnal manual, tutup periode, moderasi, akun | Muncul di `/panel/audit`; tidak ada password/token mentah di tabel `audit_events` |

### 16 — Keamanan dan upload (PRD §10)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| SEC-01 | P0 | POST form panel tanpa token CSRF | Ditolak, data tidak berubah |
| SEC-02 | P0 | Akses objek milik lain: kasir membuka bukti pengeluaran kasir lain `/panel/expenses/{id}/evidence` | Ditolak |
| SEC-03 | P0 | Upload produk/banner: SVG, `.php` diganti nama `.png`, > batas ukuran | Ditolak |
| SEC-04 | P1 | Nama file tersimpan | Acak, bukan nama asli |
| SEC-05 | P1 | Input berisi `<script>` di nama produk/komentar ulasan | Ter-escape di HTML |
| SEC-06 | P1 | Header respons | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` ada |
| SEC-07 | P2 | AC-21 HTTPS | Di luar E2E lokal; jalankan manual di staging sesuai DEPLOYMENT-UAT.md |

### 17 — Mobile (`@mobile`)

| ID | Prioritas | Skenario | Hasil |
| --- | --- | --- | --- |
| MOB-01 | P1 | Beranda, detail produk, form ulasan di 390 px | Tidak ada scroll horizontal (`document.documentElement.scrollWidth <= innerWidth`) |
| MOB-02 | P1 | POS di 390 px | Bisa menambah produk dan membayar |
| MOB-03 | P2 | Tap target tombol utama | ≥ 44 px |

## 6. Fase pengerjaan

Selesaikan dan jalankan hijau setiap fase sebelum lanjut.

1. **Fondasi:** `playwright.config.ts`, `global-setup.ts`, `fixtures.ts`, `helpers/db.ts`, `helpers/money.ts`, `helpers/flash.ts`. Bukti: spec `01` AUTH-01/02/06 lulus.
2. **Akses:** sisa spec `01`, `16` SEC-01/02.
3. **Data builder:** `helpers/data.ts` + spec `03`, `04`.
4. **POS inti:** spec `11` SHF-01, spec `05`, spec `06`.
5. **Kontrol keuangan:** spec `07`, `08`, `09`, `10`, sisa `11`.
6. **Pelaporan & publik:** `helpers/pdf.ts` + spec `12`, `02`, `13`, `14`.
7. **Pelengkap:** spec `15`, sisa `16`, `17`.
8. **Laporan:** isi tabel status AC di §8.

## 7. Aturan untuk agent

- Bahasa UI aplikasi Bahasa Indonesia; teks assertion memakai teks persis dari view (`app/Views/**`). Baca view sebelum menulis selector.
- Bila test gagal karena bug aplikasi, **jangan ubah test agar lulus dan jangan perbaiki kode aplikasi tanpa izin**. Tandai `test.fail()` dengan komentar berisi ID AC dan ringkasan bug, lalu laporkan.
- Bila perilaku aplikasi berbeda dari PRD tetapi masuk akal (misalnya penolakan lewat redirect alih-alih 403), ikuti perilaku aplikasi dan catat perbedaannya di laporan.
- Perubahan pada kode aplikasi yang diizinkan hanya penambahan `data-testid`.
- Jangan commit `e2e/.auth/`, `test-results/`, `playwright-report/`.
- Jalankan `composer test` setelah selesai untuk memastikan PHPUnit yang sudah ada tetap lulus.
- Perintah standar:

```text
npm run test:e2e
npx playwright test e2e/specs/05-pos-checkout.spec.ts
npx playwright show-report
```

## 8. Format laporan akhir

Agent menyerahkan tabel ini (format sesuai DEPLOYMENT-UAT.md):

| AC | Spec/test | Status (`Lulus`/`Gagal`/`Belum diuji`) | Catatan / bug |
| --- | --- | --- | --- |
| AC-01 | 01 AUTH-06 | | |
| AC-02 | 01 AUTH-07..09 | | |
| AC-03 | 03 CAT-03 | | |
| AC-04 | 05 POS-01 | | |
| AC-05 | 05 POS-02 | | |
| AC-06 | 05 POS-03 | | |
| AC-07 | 05 POS-04 | | |
| AC-08 | 06 CON-01 | | |
| AC-09 | — (PHPUnit) | | |
| AC-10 | 08 OPN-01 | | |
| AC-11 | 05 POS-05 | | |
| AC-12 | 07 REF-01 | | |
| AC-13 | 07 REF-02 | | |
| AC-14 | 09 EXP-01/02 | | |
| AC-15 | 04 INV-01/02 | | |
| AC-16 | 12 RPT-01 | | |
| AC-17 | 12 RPT-02 | | |
| AC-18 | 13 REV-01/02 | | |
| AC-19 | 13 REV-03..05 | | |
| AC-20 | 14 BAN-02 | | |
| AC-21 | — (manual staging) | | |
| AC-22 | 10 ACC-03 | | |
| AC-23 | 11 SHF-02/03 | | |
| AC-24 | 12 RPT-03/04 | | |
| AC-25 | — (manual restore) | | |

Sertakan juga: jumlah test lulus/gagal/skip, durasi run, daftar `test.fail()` beserta bug, dan perbedaan perilaku vs PRD.
