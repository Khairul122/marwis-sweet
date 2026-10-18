import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { root } from './env.mjs';

const read = (path) => JSON.parse(readFileSync(resolve(root, path), 'utf8'));
const main = read('test-results/results.json');
const evaluation = read('writable/e2e/evaluation-results.json');
const pdf = read('writable/e2e/evaluation-pdf.json');
const state = read('writable/e2e/full-run-state.json');
const runtime = read('writable/e2e/runtime-summary.json');
const verification = read('writable/e2e/verification-summary.json');
function flatten(suites, output = []) {
  for (const suite of suites) {
    for (const spec of suite.specs ?? []) {
      for (const test of spec.tests) {
        const result = test.results.at(-1);
        output.push({ title: spec.title, file: spec.file, project: test.projectName,
          status: result?.status, expected: test.expectedStatus, outcome: test.status,
          duration: result?.duration ?? 0 });
      }
    }
    flatten(suite.suites ?? [], output);
  }
  return output;
}
const tests = flatten(main.suites);
const extra = flatten(evaluation.suites);
const passed = tests.filter((test) => test.status === 'passed' && test.expected === 'passed');
const failures = tests.filter((test) => test.status === 'failed' && test.expected === 'failed');
const skipped = tests.filter((test) => test.status === 'skipped');
const unexpected = tests.filter((test) => test.outcome === 'unexpected' || test.outcome === 'flaky');
const status = (test) => test.status === 'skipped' ? 'Belum diuji' : test.status === 'passed' && test.expected === 'passed' ? 'Lulus' : 'Gagal';
const acStatus = (patterns, pool = tests) => {
  const selected = pool.filter((test) => patterns.some((pattern) => test.title.startsWith(pattern)));
  if (!selected.length || selected.some((test) => test.status === 'skipped')) return 'Belum diuji';
  return selected.every((test) => status(test) === 'Lulus') ? 'Lulus' : 'Gagal';
};
const ac = [
  ['AC-01', '01 AUTH-06; 02 PUB-01/05; 14 BAN-01', acStatus(['AUTH-06', 'PUB-01', 'BAN-01']), 'Katalog/banner dapat diakses tamu; panel, POS, dan PDF panel menolak tamu.'],
  ['AC-02', '01 AUTH-07/08/09', acStatus(['AUTH-07', 'AUTH-08', 'AUTH-09']), 'Grup owner dan POST dengan CSRF sah menolak kasir; PDF kasir tanpa HPP/laba.'],
  ['AC-03', '03 CAT-03', acStatus(['CAT-03']), 'Harga, resep, dan HPP transaksi lama tetap. CAT-04 menemukan bug penghapusan terpisah.'],
  ['AC-04', '05 POS-01', acStatus(['POS-01']), 'Rp50.000 dikurangi diskon Rp5.000; total Rp45.000, kembalian Rp5.000.'],
  ['AC-05', '05 POS-02; 12 RPT-01/05', acStatus(['POS-02']), 'Satu penjualan dan dua alokasi benar; label dikonfirmasi manual tidak tersedia (BUG-E2E-003).'],
  ['AC-06', '05 POS-03', acStatus(['POS-03']), 'Bahan berkurang 200 g; satu jurnal gabungan penjualan/HPP, tanpa duplikasi baris HPP.'],
  ['AC-07', '05 POS-04', acStatus(['POS-04']), 'Klik ganda dan replay idempotency key menunjuk penjualan sama.'],
  ['AC-08', '06 CON-01', acStatus(['CON-01']), 'Dua browser context, dua kasir/shift, dua proses PHP; satu berhasil dan stok akhir nol.'],
  ['AC-09', '05 POS-12; PHPUnit', 'Belum diuji', 'Fault injection kegagalan jurnal saat finalize tidak tersedia lewat UI. Suite PHPUnit yang ada tidak secara khusus membuktikan kasus ini.'],
  ['AC-10', '08 OPN-01/06', acStatus(['OPN-01']), 'Stok 10 menjadi 8; approve ulang tidak menggandakan mutasi/jurnal.'],
  ['AC-11', '05 POS-05', acStatus(['POS-05']), 'Opname yang dimulai setelah keranjang dibuat menolak pembayaran.'],
  ['AC-12', '07 REF-01/05', acStatus(['REF-01']), 'Refund membalik dana; bahan resep tidak kembali.'],
  ['AC-13', '07 REF-02', acStatus(['REF-02']), 'Barang siap jual kembali dan HPP dibalik memakai biaya transaksi asal.'],
  ['AC-14', '09 EXP-01/02', acStatus(['EXP-01']), 'Approve belum mengurangi kas; pay membuat satu jurnal dan mengurangi kas Rp10.000.'],
  ['AC-15', '04 INV-01/02', acStatus(['INV-01']), 'Penerimaan 1 kg bernilai Rp12.000 menambah persediaan tanpa beban operasional.'],
  ['AC-16', '12 RPT-01/05', acStatus(['RPT-01']), 'Total panel dan PDF harian/bulanan/tahunan cocok; pembayaran gabungan tidak dihitung ganda.'],
  ['AC-17', '12 RPT-02', acStatus(['RPT-02']), 'Refund hari ini dari penjualan bulan lalu muncul dengan nomor asal.'],
  ['AC-18', '13 REV-01/02/08', acStatus(['REV-01']), 'Tamu unggah JPEG; pending, terbit setelah approve, rata-rata/jumlah ulasan berubah.'],
  ['AC-19', '13 REV-03/04/05', acStatus(['REV-03', 'REV-04', 'REV-05']), 'Duplikasi, token kedaluwarsa, JPG palsu, SVG, dan foto besar ditolak.'],
  ['AC-20', '14 BAN-02', acStatus(['BAN-02']), 'Banner nonaktif, lewat jadwal, dan belum waktunya tidak tampil.'],
  ['AC-21', '16 SEC-07', 'Belum diuji', 'HTTPS, sertifikat, Secure cookie, dan proxy perlu staging.'],
  ['AC-22', '10 ACC-03/04', acStatus(['ACC-03']), 'Periode tertutup menolak posting, lalu dapat dibuka dengan alasan.'],
  ['AC-23', '11 SHF-01/02/03/04', acStatus(['SHF-01', 'SHF-03']), 'Kas seharusnya Rp235.000; fisik Rp230.000; jurnal hanya setelah approve. Penolakan review juga diuji.'],
  ['AC-24', '12 RPT-03; RPT-04/06 evaluasi terpisah', acStatus(['RPT-03'], tests) === 'Lulus' ? acStatus(['RPT-04'], extra) : 'Gagal', `Periode kosong valid; ${pdf.transactions} transaksi menghasilkan ${pdf.pages} halaman, header berulang dan nomor halaman.`],
  ['AC-25', 'Manual restore UAT', 'Belum diuji', 'Prosedur backup/restore deployment beserta RPO/RTO belum diuji. Pemulihan insiden lokal didokumentasikan terpisah.'],
];
const bugs = [
  ['BUG-E2E-001', 'AUTH-12 / FR-01', 'P1', 'Login dari /panel/reports selalu menuju /panel; tujuan awal hilang.'],
  ['BUG-E2E-002', 'CAT-04 / FR-03', 'Kritis', 'Produk yang telah terjual dapat dihapus. ON DELETE CASCADE menghapus sale_items sementara baris sales tetap ada.'],
  ['BUG-E2E-003', 'POS-02 / AC-05', 'P0 pada plan', 'Pembayaran gabungan benar secara nominal, tetapi UI tidak menampilkan label dikonfirmasi manual.'],
  ['BUG-E2E-004', 'POS-08 / FR-06', 'P1', 'Konfirmasi native pembatalan pesanan held diterima, tetapi POST tidak terkirim dan status tetap held.'],
  ['BUG-E2E-005', 'ACC-06 / FR-08', 'Data awal', 'DevelopmentSeeder mengisi persediaan demo Rp2.373.000 tanpa jurnal saldo awal; rekonsiliasi total tidak cocok meskipun semua jurnal posted seimbang.'],
];
const table = (headers, rows) => [
  `| ${headers.join(' | ')} |`, `| ${headers.map(() => '---').join(' | ')} |`,
  ...rows.map((row) => `| ${row.map((cell) => String(cell).replaceAll('|', '\\|').replaceAll('\n', ' ')).join(' | ')} |`),
].join('\n');
const phpunit = readFileSync(resolve(root, 'writable/e2e/phpunit.log'), 'utf8');
const phpunitSummary = phpunit.match(/OK \(\d+ tests?, \d+ assertions?\)/)?.[0] ?? phpunit.trim().split(/\r?\n/).slice(-5).join(' ');
const minutes = (ms) => `${(ms / 1000).toFixed(1)} detik`;
const text = `# Hasil E2E Marwis Sweet

Run utama: ${new Date(main.stats.startTime).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB. Schema: \`marwis_sweet_e2e\`. Browser: Chromium; mobile 390 x 844. Workers: 1, CSRF aktif, tanpa perubahan kode aplikasi.

## Ringkasan

${table(['Hasil run utama', 'Jumlah'], [['Tes terdaftar', tests.length], ['Lulus sesuai perilaku yang diharapkan', passed.length], ['Gagal karena bug yang sudah direproduksi (test.fail)', failures.length], ['Kegagalan tak terduga', unexpected.length], ['Dilewati', skipped.length]])}

Durasi run utama: ${minutes(main.stats.duration)}. Evaluasi PDF terpisah: ${extra.filter((test) => status(test) === 'Lulus').length} lulus, ${minutes(evaluation.stats.duration)}. PHPUnit: ${phpunitSummary}. TypeScript diperiksa dengan \`npm run test:e2e:types\`.

Pemeriksaan akhir database: ${state.unbalancedJournals} jurnal tidak seimbang; selisih akun persediaan tepat Rp${Number(state.difference).toLocaleString('id-ID')}, seluruhnya sesuai stok demo tanpa jurnal awal. Lampiran runtime dari ${runtime.runtimeRecords} sesi halaman mencatat ${runtime.errors} error JavaScript. Delapan tes terpilih diulang dengan trace aktif: tiga lulus dan lima bug yang sama tereproduksi, tanpa kegagalan baru. Pengulangan tersebut juga memeriksa HTML mentah penolakan kasir dan keseimbangan jurnal setelah pembayaran mobile.

Playwright memasukkan kegagalan \`test.fail()\` ke hasil yang diharapkan. Lima skenario tersebut tetap berstatus Gagal dalam laporan ini. Hasil ini belum memenuhi seluruh kriteria rilis.

## Status acceptance criteria

${table(['AC', 'Spec/test', 'Status', 'Catatan / bug'], ac)}

## Temuan yang tidak diperbaiki

${table(['ID', 'Skenario', 'Prioritas / dampak', 'Hasil aktual'], bugs)}

Assertion asli tetap dipertahankan dan setiap temuan memiliki \`test.fail()\` beserta komentar ID. BUG-E2E-002 memengaruhi riwayat transaksi dan perlu ditangani sebelum rilis.

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
- [Laporan HTML terakhir](../playwright-report/index.html), JSON lengkap di \`test-results/results.json\`.
- Trace dan screenshot kegagalan ada di \`test-results/\`. Lampiran runtime mencatat error JavaScript, warning/error console, dan respons HTTP gagal, termasuk 403/404 yang sengaja diuji.
- Trace dan screenshot lima temuan yang sudah diketahui tersimpan di \`writable/e2e/verification-tests/\`. Playwright tidak mempertahankan trace \`retain-on-failure\` untuk kegagalan yang sudah ditandai \`test.fail()\`; pengulangan khusus memakai \`--trace on\`.
- PDF evaluasi: \`writable/e2e/evaluation-sales.pdf\`; jumlah halaman dan header: \`writable/e2e/evaluation-pdf.json\`; hasil run terpisah: \`writable/e2e/evaluation-results.json\`.
- Keenam halaman PDF dirender dan diperiksa secara visual; tabel, nominal, header berulang, serta footer terbaca. PNG ada di \`writable/e2e/pdf-review/\`. Tidak ada blok teks di luar batas halaman.
- Log PHPUnit: \`writable/e2e/phpunit.log\`.
- [Insiden persiapan dan pemulihan database lokal](E2E-INCIDENT-2026-10-18.md). Database lokal dipulihkan ke keadaan sebelum kejadian; fingerprint 41 tabel diverifikasi terhadap rekonstruksi binary log.

## Daftar tes run utama

${table(['Project', 'Tes', 'Status', 'Durasi'], tests.map((test) => [test.project, test.title, status(test), minutes(test.duration)]))}
`;
writeFileSync(resolve(root, 'docs/E2E-TEST-RESULTS.md'), text);
writeFileSync(resolve(root, 'docs/e2e-results-summary.json'), JSON.stringify({
  startedAt: main.stats.startTime, durationMs: main.stats.duration, schema: 'marwis_sweet_e2e',
  total: tests.length, passed: passed.length, knownFailures: failures.length, unexpected: unexpected.length,
  skipped: skipped.length, evaluation: { tests: extra.map((test) => ({ title: test.title, status: status(test) })), ...pdf },
  state, runtime, verification,
  phpunit: phpunitSummary, acceptanceCriteria: ac.map(([id, spec, status, notes]) => ({ id, spec, status, notes })),
  bugs: bugs.map(([id, scenario, priority, actual]) => ({ id, scenario, priority, actual })), tests,
}, null, 2));
console.log(`Laporan: ${passed.length} lulus, ${failures.length} kegagalan diketahui, ${unexpected.length} kegagalan tak terduga, ${skipped.length} dilewati.`);
