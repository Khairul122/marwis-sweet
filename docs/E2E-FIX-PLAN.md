# Rencana Perbaikan Hasil E2E — Marwis Sweet

Sumber: [E2E-TEST-RESULTS.md](E2E-TEST-RESULTS.md) (run 18/10/2026). Dokumen ini instruksi kerja untuk AI agent. Akar masalah setiap bug sudah ditelusuri ke kode; agent tidak perlu menyelidiki ulang, cukup verifikasi lalu kerjakan.

## 1. Ringkasan

| ID | Prioritas | Akar masalah | Perbaikan inti |
| --- | --- | --- | --- |
| BUG-E2E-002 | **Kritis, kerjakan pertama** | Urutan argumen `addForeignKey` salah di semua migration: 55 FK jadi `ON DELETE CASCADE`, bukan `RESTRICT`/`SET NULL` | Migration baru yang menukar aturan FK + guard di `deleteProduct` |
| BUG-E2E-004 | P1 | `requestSubmit()` dipanggil di dalam event `submit` yang sama; Chrome mengabaikannya | Perbaiki cabang fallback di `app.js` + tambah dialog konfirmasi di layout POS |
| BUG-E2E-001 | P1 | URL tujuan tidak pernah disimpan saat tamu diarahkan ke login | Simpan URL di `RequireLogin`, pakai di `AuthController::attempt` |
| BUG-E2E-003 | P1 (P0 di plan E2E) | Struk menampilkan metode bayar mentah tanpa label | Label metode + "dikonfirmasi manual" di struk HTML dan PDF |
| BUG-E2E-005 | P2 (data dev) | Seeder menulis saldo stok langsung ke `inventory_items` tanpa mutasi `opening` dan jurnal | Seeder memposting saldo awal lewat mutasi + jurnal |

Setelah kelima bug selesai, lanjut ke celah cakupan di §4.

## 2. Aturan kerja

- Satu bug = satu commit. Urutan: 002 → 004 → 001 → 003 → 005 → §4.
- Setelah setiap perbaikan: hapus `test.fail(...)` beserta komentar `BUG-E2E-00x` di spec terkait, lalu jalankan spec itu sampai hijau. Assertion asli **jangan diubah**.
- Jalankan `composer test` dan `npm run test:e2e` penuh setelah BUG-002 (perubahan skema) dan sekali lagi di akhir.
- Jangan jalankan migration baru ke `marwis_sweet` sebelum backup: `mysqldump … marwis_sweet > writable/backup-before-fk-fix.sql`. Uji dulu di `marwis_sweet_e2e`.
- Ikuti `AGENTS.md` untuk perubahan UI/teks (antislop). Teks UI dalam Bahasa Indonesia.

## 3. Perbaikan per bug

### BUG-E2E-002 — Riwayat transaksi bisa terhapus (Kritis)

**Akar masalah.** Signature CI4 (`vendor/codeigniter4/framework/system/Database/Forge.php:426`):

```php
addForeignKey($fieldName, $tableName, $tableField, string $onUpdate = '', string $onDelete = '', string $fkName = '')
```

Argumen ke-4 adalah **ON UPDATE**, ke-5 **ON DELETE**. Semua migration menulis `addForeignKey('x', 't', 'id', 'RESTRICT', 'CASCADE')` dengan maksud "delete RESTRICT, update CASCADE". Hasil sebenarnya terbalik. Migration raw SQL (`000008`, `000019`) menunjukkan maksud aslinya: `ON DELETE RESTRICT ON UPDATE CASCADE`.

Kondisi aktual di `marwis_sweet` dan `marwis_sweet_e2e` (dari `information_schema.REFERENTIAL_CONSTRAINTS`):

| DELETE_RULE | UPDATE_RULE | Jumlah | Status |
| --- | --- | --- | --- |
| CASCADE | RESTRICT | 53 | **Salah**, seharusnya RESTRICT / CASCADE |
| CASCADE | SET NULL | 2 | **Salah**, seharusnya SET NULL / CASCADE |
| CASCADE | CASCADE | 15 | Benar (ditulis `'CASCADE','CASCADE'`) |
| RESTRICT | CASCADE | 7 | Benar (raw SQL) |
| SET NULL | CASCADE | 1 | Benar (raw SQL) |

Dampaknya bukan hanya produk. Contoh: menghapus `users` ikut menghapus `sales`, `shifts`, `journal_entries`, `refunds`; menghapus `accounts` menghapus `journal_lines`; menghapus `inventory_items` menghapus `stock_movements`; menghapus `shifts` menghapus `sales`. Saat ini route hapus user/akun/shift tidak ada, jadi yang bisa terjadi lewat UI baru penghapusan produk (CAT-04). Tetapi skema tidak melindungi riwayat keuangan sama sekali.

Database dev sudah dicek: 156 `sales`, 0 penjualan tanpa `sale_items`. Belum ada data yang hilang.

**Langkah.**

1. Buat `app/Database/Migrations/2026-10-18-000024_FixForeignKeyDeleteRules.php`:
   - `up()`: query `information_schema.REFERENTIAL_CONSTRAINTS` + `KEY_COLUMN_USAGE` untuk schema aktif (`$this->db->getDatabase()`), ambil constraint dengan `(DELETE_RULE, UPDATE_RULE)` = `('CASCADE','RESTRICT')` atau `('CASCADE','SET NULL')`. Untuk tiap constraint: `ALTER TABLE t DROP FOREIGN KEY name, ADD CONSTRAINT name FOREIGN KEY (col) REFERENCES ref(refcol) ON DELETE <update_rule lama> ON UPDATE CASCADE`. Satu `ALTER TABLE` per tabel.
   - Kombinasi itu aman dijadikan pemilih karena tidak ada migration yang sengaja menulis delete CASCADE + update RESTRICT/SET NULL. Verifikasi dengan `grep -n "addForeignKey" app/Database/Migrations/*.php`: setiap panggilan dengan argumen ke-4 `RESTRICT` atau `SET NULL` adalah target.
   - `down()`: kebalikannya (RESTRICT/CASCADE → CASCADE/RESTRICT, SET NULL/CASCADE → CASCADE/SET NULL), hanya untuk constraint yang diubah `up()`. Simpan daftar nama constraint di konstanta, hasil dari langkah verifikasi.
   - Jangan mengedit migration lama yang sudah dijalankan.
2. Tambah guard di `app/Controllers/Panel/CatalogController.php:426` (`deleteProduct`), pola sama dengan `deleteCategory` (baris 419): bila `sale_items.product_id` atau `product_reviews.product_id` punya baris, kembalikan error "Produk sudah punya riwayat transaksi atau ulasan…" tanpa mencoba delete. FK menjadi pengaman kedua.
3. Periksa semua aksi hapus yang selama ini mungkin bergantung pada cascade, lalu pastikan tetap berfungsi atau gagal dengan pesan jelas (bukan exception 500):
   - `CatalogController::deleteProduct`, `deleteCategory`, `deleteImage`
   - `MasterDataController::deleteItem` (baris 195; cek `unit_conversions`, `recipe_items`, `opname_suggestions`), `deleteSupplier`, `deleteTopping`, `removeConversion`, `removeRecipeItem`, `removeToppingItem`
   - `SettingsController::deleteBanner`

   Bila bahan yang belum dipakai tapi punya `unit_conversions` kini gagal dihapus, hapus dulu baris anak yang memang milik item itu (konversi, usulan opname) di dalam transaksi yang sama, jangan kembalikan cascade.
4. Tambah PHPUnit `tests/database/ForeignKeyDeleteRulesTest.php`: assert tidak ada constraint dengan `DELETE_RULE='CASCADE' AND UPDATE_RULE IN ('RESTRICT','SET NULL')`, dan menghapus `products` yang punya `sale_items` melempar exception.

**Verifikasi.** Hapus `test.fail` di `e2e/specs/03-catalog-master.spec.ts:80-81`. CAT-04 lulus. Jalankan suite penuh, karena perubahan FK bisa memengaruhi spec yang membersihkan data.

### BUG-E2E-004 — Batal pesanan ditahan tidak terkirim

**Akar masalah.** `app/Views/layouts/pos.php` tidak punya `<dialog data-confirm-dialog>` (hanya ada di `layouts/panel.php:74`). Akibatnya `public/assets/js/app.js:266-268` memakai fallback:

```js
event.preventDefault();
if (!confirmDialog || …) {
    if (window.confirm(…)) { form.dataset.confirmed = '1'; form.requestSubmit(event.submitter); }
```

`requestSubmit()` dipanggil saat event `submit` form yang sama masih berjalan. Spesifikasi HTML ("firing submission events" flag) membuat browser mengabaikan submit bersarang ini tanpa error. Cabang dialog (baris 272) tidak kena karena `onAccept` berjalan di event klik terpisah.

Semua form `data-confirm-form` di halaman POS kena, dan juga panel bila `showModal` tidak tersedia.

**Langkah.**

1. Perbaiki fallback di `app.js` (perbaikan di fungsi bersama, berlaku untuk semua pemanggil):

   ```js
   if (!confirmDialog || typeof confirmDialog.showModal !== 'function') {
       if (!window.confirm(form.dataset.confirmForm)) event.preventDefault();
       return;
   }
   event.preventDefault();
   ```

   (`preventDefault` dipindah setelah pengecekan fallback; submit asli dibiarkan jalan bila dikonfirmasi.)
2. Salin markup dialog konfirmasi dari `layouts/panel.php:74` (beserta tombol accept/cancel-nya) ke `layouts/pos.php`, supaya POS memakai dialog yang sama dengan panel.
3. Pastikan `<dialog>` POS tidak tertutup elemen `inert` (cart `<aside … inert>`): letakkan sebagai anak langsung `<body>`.

**Verifikasi.** Test POS-08 saat ini mengandalkan `dialog.accept()` (dialog native). Setelah langkah 2, konfirmasi memakai `<dialog>` HTML, jadi ubah **hanya langkah interaksinya** menjadi klik tombol konfirmasi di dialog. Assertion (status `cancelled`, flash, hitungan tabel tidak berubah) tetap. Hapus `test.fail` di `e2e/specs/05-pos-checkout.spec.ts:139-140`. Tambah satu test kecil yang menjalankan fallback (`page.evaluate(() => HTMLDialogElement.prototype.showModal = undefined)` sebelum klik) supaya cabang `window.confirm` juga tercakup.

**Catatan PRD (perlu keputusan owner, jangan dikerjakan tanpa izin).** FR-06 meminta pembatalan keranjang "dengan alasan", tetapi tabel `pos_orders` (migration `000020`) tidak punya kolom alasan dan form tidak punya isian. Ini kekurangan fitur, bukan bagian bug ini.

### BUG-E2E-001 — Login tidak kembali ke halaman tujuan

**Akar masalah.** `app/Filters/RequireLogin.php` me-redirect ke login tanpa menyimpan URL asal. `AuthController::attempt()` selalu `redirect()->route('panel')`. Filter `ReturnTo` hanya untuk field `_return` setelah POST sukses, bukan untuk login.

**Langkah.**

1. `RequireLogin::before`: bila belum login **dan** request GET, simpan path relatif (`uri_string()` + query string) ke `session()->set('login_intended', …)`. Jangan simpan untuk POST atau URL PDF yang dipanggil `fetch`.
2. `AuthController::attempt`: ambil `login_intended` **sebelum** `session()->regenerate(true)`, hapus dari sesi, lalu redirect ke situ hanya bila cocok `#^panel(/[A-Za-z0-9/_.\-]*)?(\?[^\r\n]*)?$#`. Selain itu ke `route('panel')`. Ini mencegah open redirect (`//evil.com`, `http://…`).
3. Pastikan filter role tetap berlaku: kasir yang tujuannya `/panel/accounting` akan diarahkan filter `owner` seperti biasa.

**Verifikasi.** Hapus `test.fail` AUTH-12 di `e2e/specs/01-auth-access.spec.ts`. Tambah dua kasus di spec yang sama: `login_intended` berisi URL eksternal tetap ke `/panel`; login langsung dari `/login` tetap ke `/panel`.

### BUG-E2E-003 — Label "dikonfirmasi manual" tidak ada

**Akar masalah.** `app/Views/panel/transactions/receipt.php:74` dan `receipt_pdf.php:67` mencetak `ucwords(str_replace('_', ' ', $payment['method']))` (contoh "Bank Transfer", "Qris") tanpa penanda konfirmasi manual (FR-06).

**Langkah.**

1. Buat satu helper label metode (misalnya `app/Helpers/payment_helper.php` dengan `payment_method_label(string $method): string` → Tunai, QRIS, Transfer bank, …), selaras dengan opsi di `receipt.php:95`. Daftarkan di `BaseController::$helpers` atau `Autoload` sesuai pola proyek.
2. Di kedua view: tampilkan label; untuk metode selain `cash`, tambahkan "· dikonfirmasi manual" dan nomor referensi bila kolomnya ada di `$payments`.
3. Cek tampilan lain yang menampilkan metode bayar (daftar transaksi, laporan, refund `receipt.php:114`) dan pakai helper yang sama.

**Verifikasi.** Hapus `test.fail` POS-02 di `e2e/specs/05-pos-checkout.spec.ts`. AC-05 berubah menjadi Lulus.

### BUG-E2E-005 — Persediaan demo tanpa jurnal saldo awal

**Akar masalah.** `app/Database/Seeds/TemporaryDataSeeder.php:37-46` mengisi `quantity_on_hand`, `average_cost`, `inventory_value` langsung tanpa `stock_movements` bertipe `opening` dan tanpa jurnal. Total Rp2.373.000 tidak tercatat di akun persediaan, sehingga ACC-06 gagal.

**Langkah.**

1. Ubah seeder: buat item dengan stok 0, lalu posting saldo awal per item dengan logika yang sama seperti `StockAdjustmentService` tipe `opening` (`app/Services/StockAdjustmentService.php:447-456`): mutasi `opening` + jurnal Debit `inventory` / Kredit akun ekuitas saldo awal (cari `system_key` modal/saldo awal di seeder akun). Pakai `AccountingService::post()` agar keseimbangan divalidasi.
2. Idempoten: lewati item yang sudah punya mutasi `opening`. Seeder harus aman dijalankan ulang tanpa jurnal ganda.
3. Untuk database dev yang sudah terisi, jangan mengoreksi otomatis. Tulis catatan di README: jalankan `migrate:refresh --all` + `DevelopmentSeeder`, atau buat penyesuaian lewat UI.

**Verifikasi.** Hapus `test.fail` ACC-06 di `e2e/specs/10-accounting.spec.ts`. Selisih akun persediaan menjadi Rp0.

## 4. Celah cakupan (setelah bug selesai)

| ID | Prioritas | Kekurangan | Rencana |
| --- | --- | --- | --- |
| GAP-01 | P0 | AC-09 belum diuji | Fault injection alami: tutup periode akuntansi berjalan, lalu finalisasi POS. Posting jurnal ke periode tertutup ditolak, jadi transaksi harus rollback. Assert `sales`, `payments`, `stock_movements`, `journal_entries` tidak bertambah dan stok tetap. Kerjakan sebagai PHPUnit (`SaleService`) **dan** E2E POS-12. Bila `SaleService` ternyata tidak memeriksa periode, laporkan sebagai bug baru |
| GAP-02 | P1 | Jurnal tidak seimbang tidak bisa diuji dari UI (form satu nominal) | PHPUnit untuk `AccountingService::post()` (`app/Services/AccountingService.php:53`): baris tidak seimbang, baris debit+kredit sekaligus, baris nol → exception |
| GAP-03 | P1 | Validasi server untuk batas form belum dibuktikan | E2E level request (`page.request.post` dengan cookie + CSRF dari halaman) yang melewati atribut HTML: refund melebihi kuantitas, pengeluaran nominal 0, komentar ulasan < 10 / > 1.000 karakter, rating 0 dan 6, foto produk ke-6, 4 foto ulasan. Semua harus ditolak server. Setiap yang lolos dicatat sebagai bug baru, tanpa diperbaiki di langkah ini |
| GAP-04 | P2 | Pagination refund di browser | Tambah ke RPT-06 dengan fixture `EvaluationDataSeeder` |
| GAP-05 | P2 | P2 yang dilewati: urutan foto, usulan opname, kategori pengeluaran nonaktif, konsistensi dashboard, EXIF GPS, tap target | Kerjakan bila waktu ada; untuk EXIF siapkan JPEG fixture ber-GPS di `e2e/fixtures/` |
| GAP-06 | — | AC-21 (HTTPS) dan AC-25 (restore) | Manual di staging sesuai `DEPLOYMENT-UAT.md`, di luar lingkup agent |

## 5. Definition of Done

- Lima `test.fail` BUG-E2E-001..005 dihapus dan test-nya lulus tanpa assertion diubah.
- Migration `000024` sudah jalan di `marwis_sweet_e2e` dan `marwis_sweet` (setelah backup); query di §3 BUG-002 menghasilkan 0 constraint salah.
- `composer test` dan `npm run test:e2e` hijau.
- GAP-01..03 punya test; bug baru dari situ tercatat dengan `test.fail()` + ID `BUG-E2E-006` dst.
- `docs/E2E-TEST-RESULTS.md` diperbarui: ringkasan, status AC (AC-05 dan AC-09), tabel temuan diberi kolom "Diperbaiki di commit".
