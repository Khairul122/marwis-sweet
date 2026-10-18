import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { root } from './env.mjs';

const read = (path) => JSON.parse(readFileSync(resolve(root, path), 'utf8'));
const main = read('test-results/results.json');
const evaluation = read('writable/e2e/evaluation-results.json');
const pdf = read('writable/e2e/evaluation-pdf.json');
const state = read('writable/e2e/full-run-state.json');
const runtime = { runtimeRecords: 0, errors: 0, consoleMessages: 0, responses: [] };
function flatten(suites, output = []) {
  for (const suite of suites) {
    for (const spec of suite.specs ?? []) {
      for (const test of spec.tests) {
        const result = test.results.at(-1);
        for (const attachment of result?.attachments ?? []) {
          if (!attachment.name.startsWith('runtime-') || attachment.contentType !== 'application/json') continue;
          const record = attachment.path ? JSON.parse(readFileSync(attachment.path, 'utf8'))
            : JSON.parse(Buffer.from(attachment.body, 'base64').toString('utf8'));
          runtime.runtimeRecords++;
          runtime.errors += record.errors.length;
          runtime.consoleMessages += record.consoleMessages.length;
          runtime.responses.push(...record.responses.map((response) => ({ test: spec.title, ...response })));
        }
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
const mainRuntime = structuredClone(runtime);
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
  ['AC-03', '03 CAT-03/04; ForeignKeyDeleteRulesTest', acStatus(['CAT-03', 'CAT-04']), 'Snapshot harga/resep/HPP tetap; penghapusan produk terjual ditolak controller dan FK.'],
  ['AC-04', '05 POS-01', acStatus(['POS-01']), 'Rp50.000 dikurangi diskon Rp5.000; total Rp45.000, kembalian Rp5.000.'],
  ['AC-05', '05 POS-02; 12 RPT-01/05', acStatus(['POS-02']), 'Satu penjualan dan dua alokasi benar; label konfirmasi manual dan referensi ada pada HTML/PDF/laporan.'],
  ['AC-06', '05 POS-03', acStatus(['POS-03']), 'Bahan berkurang 200 g; satu jurnal gabungan penjualan/HPP, tanpa duplikasi baris HPP.'],
  ['AC-07', '05 POS-04', acStatus(['POS-04']), 'Klik ganda dan replay idempotency key menunjuk penjualan sama.'],
  ['AC-08', '06 CON-01', acStatus(['CON-01']), 'Dua browser context, dua kasir/shift, dua proses PHP; satu berhasil dan stok akhir nol.'],
  ['AC-09', '05 POS-12; SaleServiceRollbackTest', acStatus(['POS-12']), 'Periode berjalan ditutup melalui UI; jurnal ditolak dan seluruh penjualan, pembayaran, mutasi, jurnal, serta stok rollback. PHPUnit menguji transaksi top-level yang sama.'],
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
  ['AC-24', '12 RPT-03; RPT-04/06 evaluasi terpisah', acStatus(['RPT-03'], tests) === 'Lulus' ? acStatus(['RPT-04'], extra) : 'Gagal', `Periode kosong valid; ${pdf.transactions} transaksi, ${pdf.refunds} refund, ${pdf.pages} halaman PDF. Pagination transaksi/refund menjaga total; header dan nomor halaman berulang.`],
  ['AC-25', 'Manual restore UAT', 'Belum diuji', 'Prosedur backup/restore deployment beserta RPO/RTO belum diuji. Pemulihan insiden lokal didokumentasikan terpisah.'],
];
const bugs = [
  ['BUG-E2E-001', 'AUTH-12..16 / FR-01', 'P1', 'Diperbaiki', '8e3546a', 'Tujuan lokal dan query dipulihkan; tujuan eksternal ditolak dan role tetap diperiksa.'],
  ['BUG-E2E-002', 'CAT-04 / FR-03', 'Kritis', 'Diperbaiki', '0c04d14', '55 FK diperbaiki dan produk dengan riwayat ditolak saat dihapus; backup dibuat sebelum migration lokal.'],
  ['BUG-E2E-003', 'POS-02 / AC-05', 'P0 pada plan E2E', 'Diperbaiki', '43b5d4d', 'Label metode, konfirmasi manual, dan referensi konsisten pada struk serta laporan.'],
  ['BUG-E2E-004', 'POS-08 / FR-06', 'P1', 'Diperbaiki', '9ce90de', 'Dialog POS dan fallback native mengirim pembatalan; penolakan konfirmasi mempertahankan pesanan.'],
  ['BUG-E2E-005', 'ACC-06 / FR-08', 'P2 data demo', 'Diperbaiki', 'f3521bc', 'Bahan demo baru memiliki saldo awal dan jurnal modal; rerun mempertahankan stok terpakai dan legacy.'],
  ['BUG-E2E-006', 'Setup evaluasi / GAP-04', 'P2 seeder evaluasi', 'Diperbaiki', '1254083', 'EvaluationDataSeeder menyertakan ID owner ketika menolak pengeluaran; setup evaluasi tidak lagi ArgumentCountError.'],
];
const table = (headers, rows) => [
  `| ${headers.join(' | ')} |`, `| ${headers.map(() => '---').join(' | ')} |`,
  ...rows.map((row) => `| ${row.map((cell) => String(cell).replaceAll('|', '\\|').replaceAll('\n', ' ')).join(' | ')} |`),
].join('\n');
const phpunit = readFileSync(resolve(root, 'writable/e2e/phpunit.log'), 'utf8');
const phpunitSummary = phpunit.match(/OK \(\d+ tests?, \d+ assertions?\)/)?.[0] ?? phpunit.trim().split(/\r?\n/).slice(-5).join(' ');
const minutes = (ms) => `${(ms / 1000).toFixed(1)} detik`;
const text = `# Hasil E2E Marwis Sweet

Run verifikasi setelah perbaikan: ${new Date(main.stats.startTime).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB. Schema: \`marwis_sweet_e2e\`. Browser: Chromium; mobile 390 x 844. Workers: 1, CSRF aktif, toolbar development nonaktif khusus server E2E. Mengikuti [E2E-FIX-PLAN.md](E2E-FIX-PLAN.md).

## Ringkasan

${table(['Hasil run utama', 'Jumlah'], [['Tes terdaftar', tests.length], ['Lulus sesuai perilaku yang diharapkan', passed.length], ['Gagal karena bug yang sudah direproduksi (test.fail)', failures.length], ['Kegagalan tak terduga', unexpected.length], ['Dilewati', skipped.length]])}

Durasi run utama: ${minutes(main.stats.duration)}. Evaluasi PDF terpisah: ${extra.filter((test) => status(test) === 'Lulus').length} lulus, ${minutes(evaluation.stats.duration)}. PHPUnit: ${phpunitSummary}. TypeScript diperiksa dengan \`npm run test:e2e:types\`.

Pemeriksaan akhir schema E2E: ${state.unbalancedJournals} jurnal tidak seimbang, selisih persediaan Rp${Number(state.difference).toLocaleString('id-ID')}, ${state.reversedForeignKeys} FK terbalik. Lampiran ${mainRuntime.runtimeRecords} sesi halaman mencatat ${mainRuntime.errors} error JavaScript, ${mainRuntime.consoleMessages} pesan console warning/error, dan ${mainRuntime.responses.length} respons HTTP gagal. Respons negatif yang disengaja dicatat pada JSON ringkasan.

Kelima penanda \`test.fail()\` awal dihapus; assertion asli dipertahankan. Kolom kegagalan diketahui tetap memisahkan bug aplikasi dari status expected Playwright. AC-21 dan AC-25 masih memerlukan UAT staging sesuai DEPLOYMENT-UAT.md.

## Status acceptance criteria

${table(['AC', 'Spec/test', 'Status', 'Catatan / bug'], ac)}

## Status temuan

${table(['ID', 'Skenario', 'Prioritas', 'Status', 'Diperbaiki di commit', 'Perilaku setelah perbaikan'], bugs)}

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
- [Laporan HTML terakhir](../playwright-report/index.html), JSON lengkap di \`test-results/results.json\`.
- Trace dan screenshot kegagalan ada di \`test-results/\`. Lampiran runtime mencatat error JavaScript, warning/error console, dan respons HTTP gagal, termasuk 403/404 yang sengaja diuji.
- Bukti tahap BUG-002 ada di \`writable/e2e/bug002-results.json\`. Screenshot dialog POS serta struk desktop/mobile/PDF ada di \`writable/e2e/bug003-*\` dan \`writable/e2e/bug004-dialog.png\`.
- PDF evaluasi: \`writable/e2e/evaluation-sales.pdf\`; jumlah halaman dan header: \`writable/e2e/evaluation-pdf.json\`; hasil run terpisah: \`writable/e2e/evaluation-results.json\`.
- PDF evaluasi terbaru dirender untuk pemeriksaan tabel, nominal, header, dan footer. Hasil visual ada di \`writable/e2e/pdf-review/\`; detail halaman dicatat pada laporan perbaikan.
- Log PHPUnit: \`writable/e2e/phpunit.log\`.
- Backup sebelum migration FK: \`writable/backup-before-fk-fix.sql\`, SHA-256 \`fd7f6e7519b481445e958d131dd6a69d1739b33cf7355e834f97cf11eb9efe5d\`. Restore verifikasi 41 tabel cocok; setelah migration, 40 tabel data tetap, hanya catatan migrations bertambah. Bukti privat: \`writable/e2e/backup-before-fk-fix.json\` dan \`writable/e2e/local-fk-migration.json\`.
- [Catatan insiden sesi pengujian sebelumnya](E2E-INCIDENT-2026-10-18.md) dipertahankan sebagai riwayat. Sesi perbaikan ini memakai backup baru sebelum migration.

## Daftar tes run utama

${table(['Project', 'Tes', 'Status', 'Durasi'], tests.map((test) => [test.project, test.title, status(test), minutes(test.duration)]))}
`;
writeFileSync(resolve(root, 'docs/E2E-TEST-RESULTS.md'), text);
writeFileSync(resolve(root, 'docs/e2e-results-summary.json'), JSON.stringify({
  startedAt: main.stats.startTime, durationMs: main.stats.duration, schema: 'marwis_sweet_e2e',
  total: tests.length, passed: passed.length, knownFailures: failures.length, unexpected: unexpected.length,
  skipped: skipped.length, evaluation: { tests: extra.map((test) => ({ title: test.title, status: status(test) })), ...pdf },
  state, runtime: mainRuntime,
  phpunit: phpunitSummary, acceptanceCriteria: ac.map(([id, spec, status, notes]) => ({ id, spec, status, notes })),
  bugs: bugs.map(([id, scenario, priority, status, commit, actual]) => ({ id, scenario, priority, status, commit, actual })), tests,
}, null, 2));
console.log(`Laporan: ${passed.length} lulus, ${failures.length} kegagalan diketahui, ${unexpected.length} kegagalan tak terduga, ${skipped.length} dilewati.`);
