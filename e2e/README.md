# Menjalankan E2E Marwis Sweet

Suite mengikuti [E2E-TEST-PLAN.md](../docs/E2E-TEST-PLAN.md). Fixture operasional dibuat melalui form asli dengan CSRF aktif. GAP-03 mengirim request langsung dengan cookie dan CSRF sah untuk melewati batas HTML dan membuktikan penolakan server. Query assertion hanya membaca `marwis_sweet_e2e`; pengecualian fixture tanggal dibatasi pada `sales.paid_at` dan `review_tokens.expires_at`.

## Persiapan dan perintah

Jalankan dari root repository dengan PHP, Node.js, dan MySQL tersedia:

```text
npm ci
npx playwright install chromium
npm run test:e2e:types
npm run test:e2e
npx playwright test e2e/specs/05-pos-checkout.spec.ts
npx playwright show-report
```

Setiap run mereset schema `marwis_sweet_e2e` dan menjalankan `DevelopmentSeeder`. Nama schema dikunci dalam runner. Kredensial koneksi dibaca dari konfigurasi lokal, atau `E2E_DB_HOST`, `E2E_DB_PORT`, `E2E_DB_USER`, dan `E2E_DB_PASSWORD`. `.env` tidak ditulis ulang. `E2E_PHP` dapat menunjuk executable PHP lain.

Port 8081 dipakai server E2E. `CON-01` menyalakan proses PHP kedua pada 8082, membuat kasir dan shift berbeda, lalu mengirim pembayaran dari dua browser context melalui `Promise.all`. Dua proses diperlukan karena server PHP bawaan pada Windows menangani request secara berurutan dalam satu proses. Proses kedua dihentikan setelah tes.

Runner memeriksa konfigurasi CLI dan HTTP sebelum migrasi: schema harus tepat `marwis_sweet_e2e`, base URL harus 8081, dan filter CSRF harus aktif. Endpoint pemeriksaan hanya ada pada router di `e2e/scripts/`, di luar kode aplikasi.

Router E2E juga menyediakan fixture POST `/__e2e/login-intended` untuk menguji penolakan tujuan login eksternal. Fixture memeriksa schema E2E, nama sesi, dan CSRF; hanya field `login_intended` yang ditulis. Endpoint tidak tersedia pada server aplikasi biasa.

## Sesi dan konfigurasi Windows

Server menggunakan `CI_ENVIRONMENT=development`. Dalam mode `testing`, `Session::startSession()` CodeIgniter mengosongkan sesi setiap request sehingga login browser tidak bertahan. Migrasi/seeder CLI dan PHPUnit memakai `testing`; bootstrap menetapkan `SUPPORTPATH` agar namespace migrasi pendukung PHPUnit ikut dikenali. Reset diperiksa sebelum seeder: tabel penjualan, user, dan jurnal harus kosong.

Hanya pada PHP HTTP server E2E, bootstrap menetapkan `CI_DEBUG=false` untuk menonaktifkan toolbar development. Dua proses PHP sebelumnya berebut menghapus file history toolbar dan menimbulkan HTTP 500 pada request toolbar. Aplikasi, CSRF, dan sesi tetap diuji; konfigurasi server aplikasi biasa tidak diubah. Runtime observer menggagalkan tes bila ada JavaScript error atau HTTP 5xx, termasuk pada kasir kedua di tes concurrency.

Environment bertitik tidak terisi otomatis ke `$_ENV` pada instalasi PHP Windows yang diuji. Bootstrap E2E menerapkan override dari environment proses sebelum CodeIgniter membaca `.env`. Server memakai router pengujian dan rewrite bawaan framework; menjalankan `spark serve` langsung tidak mempertahankan seluruh override ke proses anak pada instalasi ini.

Cache throttle dan sesi disimpan di `writable/e2e/`. Snapshot login dibuat di `e2e/.auth/`; fixture browser membuat login baru per tes agar logout dan regenerasi CSRF tidak menginvalidasi sesi tes lain. Selector konten dibatasi ke area aplikasi untuk menghindari elemen toolbar development.

## Saldo awal data demo

`DevelopmentSeeder` memberi enam bahan baru mutasi `opening` dan jurnal Debit Persediaan / Kredit Modal pemilik melalui `AccountingService`. Pengulangan seeder mempertahankan stok, biaya, dan nilai bahan yang sudah ada, tanpa menggandakan saldo awal. Stok legacy tanpa jurnal tidak dikoreksi otomatis.

Untuk database development yang memang boleh dikosongkan, buat backup lebih dulu, lalu jalankan `php spark migrate:refresh --all` dan `php spark db:seed DevelopmentSeeder`. Perintah tersebut menghapus data, sehingga jangan dipakai untuk memperbaiki data operasional. Untuk database berisi riwayat, lakukan rekonsiliasi dan penyesuaian yang ditinjau owner melalui UI. Seluruh reset otomatis suite ini tetap hanya menargetkan `marwis_sweet_e2e`.

## PDF evaluasi terpisah

`RPT-04/06` dilewati pada run biasa. Jalankan fixture besar secara terpisah di PowerShell:

```powershell
$env:E2E_EVALUATION = '1'
npx playwright test e2e/specs/12-reports-pdf.spec.ts --project=chromium --grep RPT-04
Remove-Item Env:\E2E_EVALUATION
```

Run ini mereset schema E2E, lalu menambahkan `EvaluationDataSeeder` dan fixture khusus E2E `EvaluationRefundSeeder`. Sebanyak 51 refund dibuat melalui `RefundService` untuk menguji halaman kedua refund tanpa mengubah seeder aplikasi. Hasil evaluasi disimpan terpisah di `writable/e2e/evaluation-results.json` dan `writable/e2e/evaluation-report/`, sehingga hasil run utama tetap tersimpan.

## PHPUnit dan bukti

```text
node e2e/scripts/phpunit.mjs
```

Runner tersebut memeriksa target, mereset schema E2E tanpa seeder, lalu menjalankan `composer test -- --no-coverage` dengan group `default` dan `tests` menunjuk schema E2E. Schema kosong diperlukan karena feature test yang ada menghitung total seluruh transaksi pada tanggal uji. Opsi `--no-coverage` menghindari warning ketika PHP tidak memiliki driver coverage. Lokasi Composer PHAR Windows dapat diubah melalui `E2E_COMPOSER_PHAR`.

Hasil HTML ada di `playwright-report/`, JSON di `test-results/results.json`, dan log PHP di `writable/e2e/server.log`. Lampiran mencakup error JavaScript, console warning/error, respons HTTP gagal, screenshot, dan trace kegagalan. Respons 403/404 yang sengaja diuji tetap dicatat sebagai bukti skenario negatif. File auth, artefak runtime, dan upload produk/banner diabaikan Git.

Global teardown menyimpan snapshot invariant setelah setiap run: jurnal seimbang, nilai persediaan cocok dengan buku besar, dan tidak ada FK terbalik. Snapshot utama ada di `writable/e2e/full-run-state.json`; evaluasi menggunakan `writable/e2e/evaluation-state.json`. Ketidaksesuaian membuat run gagal.

`test.fail()` hanya dipakai bila ada bug baru yang telah direproduksi. Lima penanda BUG-E2E-001 sampai 005 sudah dihapus setelah perbaikan. Playwright menganggap kegagalan bertanda ini sebagai hasil yang diharapkan, tetapi laporan bisnis tetap menyebutnya **Gagal**. Tes yang tiba-tiba berhasil dengan penanda ini membuat run gagal agar penanda ditinjau ulang.

Untuk merekam trace regresi perbaikan, gunakan `--trace on`. Contoh pengulangan terpisah yang mempertahankan artefak run utama:

```text
npm run test:e2e -- --grep "AUTH-12|CAT-04|POS-02|POS-08|ACC-06" --trace on --output writable/e2e/verification-tests --reporter=list
```

`npm run test:e2e:report` menyusun laporan dari hasil run utama dan bukti evaluasi/verification di `writable/e2e/`. Laporan hasil sesi ini ada di [E2E-TEST-RESULTS.md](../docs/E2E-TEST-RESULTS.md).

Panduan konfigurasi runner: [Playwright web server](https://playwright.dev/docs/test-webserver) dan [global setup](https://playwright.dev/docs/test-global-setup-teardown).
