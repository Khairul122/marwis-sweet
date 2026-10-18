import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { createProduct, openShift, cart, pay } from '../helpers/data';
import { requestRefund, approveRefund } from '../helpers/finance';
import { pdf } from '../helpers/pdf';
import { money } from '../helpers/money';
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { root } from '../scripts/env.mjs';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('RPT-01/05 AC-16 total panel dan PDF harian, bulanan, tahunan serta filter pembayaran gabungan', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  test.setTimeout(90_000);
  const product = await createProduct(owner, db, { stockMode: 'none', price: 45000 });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id, { split: 20000, cash: 20000 });
  const sale = await pay(cashier, db);
  const dates = await db.one("SELECT DATE_FORMAT(CURDATE(),'%Y-%m-%d') day,DATE_FORMAT(CURDATE(),'%Y-%m') month,YEAR(CURDATE()) year");
  for (const [type, key, value] of [['daily', 'date', dates.day], ['monthly', 'month', dates.month], ['yearly', 'year', dates.year]]) {
    const query = `period_type=${type}&${key}=${value}&product=${product.id}`;
    await owner.goto(`/panel/reports?${query}`);
    const total = owner.locator('article').filter({ has: owner.getByText('Penjualan bersih', { exact: true }) });
    expect(money(await total.locator('p').nth(1).innerText())).toBe(45000);
    const document = await pdf(owner, `/panel/reports/sales.pdf?${query}`);
    expect(document.text).toMatch(/Rp\s*45\.000/);
    expect(document.text).toContain(sale.sale_number);
  }
  for (const method of ['cash', 'qris']) {
    await owner.goto(`/panel/reports?product=${product.id}&sale_method=${method}`);
    const count = owner.locator('article').filter({ has: owner.getByText('Transaksi lunas', { exact: true }) });
    await expect(count.locator('p').nth(1)).toHaveText('1');
  }
  expect(await db.count('payments', 'sale_id=?', [sale.id])).toBe(2);
});

test('RPT-02 AC-17 refund hari ini dari penjualan bulan lalu muncul beserta transaksi asal', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const sale = await pay(cashier, db);
  const date = await db.one("SELECT CONCAT(DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m'),'-15 12:00:00') value");
  await db.fixtureDate('sales', sale.id, 'paid_at', date.value);
  const refund = await requestRefund(cashier, db, sale.id);
  await approveRefund(owner, sale.id, refund.id);
  await owner.goto(`/panel/reports?product=${product.id}`);
  await expect(owner.getByRole('row').filter({ hasText: refund.refund_number })).toContainText(sale.sale_number);
  const document = await pdf(owner, `/panel/reports/sales.pdf?product=${product.id}`);
  expect(document.text).toContain(sale.sale_number);
  expect(document.text).toContain(refund.refund_number);
  expect(await db.count('refunds', 'id=? AND DATE(posted_at)=CURDATE()', [refund.id])).toBe(1);
});

test('RPT-03 AC-24 periode kosong menampilkan total nol dan PDF valid', async ({ ownerPage: owner, db }) => {
  await owner.goto('/panel/reports?period_type=daily&date=2000-01-01');
  await expect(owner.getByText('Tidak ada data pada periode ini.', { exact: true }).first()).toBeVisible();
  const summary = owner.locator('article').filter({ has: owner.getByText('Penjualan bersih', { exact: true }) });
  expect(money(await summary.locator('p').nth(1).innerText())).toBe(0);
  const document = await pdf(owner, '/panel/reports/sales.pdf?period_type=daily&date=2000-01-01');
  expect(document.text).toContain('Tidak ada data pada periode ini.');
  expect(document.text).toMatch(/Rp\s*0/);
  expect(await db.count('sales', "DATE(paid_at)='2000-01-01'")).toBe(0);
});

test('RPT-07 FR-09 seluruh PDF operasional menerapkan izin owner dan kasir', async ({ ownerPage: owner, cashierPage: cashier }) => {
  test.setTimeout(90_000);
  await owner.goto('/panel/reports');
  await cashier.goto('/panel/reports');
  for (const type of ['stock', 'opnames', 'shifts', 'expenses', 'finance']) {
    const path = `/panel/reports/${type}.pdf`;
    expect((await pdf(owner, path)).pages).toBeGreaterThan(0);
    if (['stock', 'opnames', 'shifts'].includes(type)) {
      expect((await pdf(cashier, path)).text).not.toMatch(/HPP|laba|buku besar/i);
    } else {
      await expect(cashier.locator(`a[href*="/${type}.pdf"]`)).toHaveCount(0);
      const response = await cashier.request.get(path, { maxRedirects: 0 });
      expect(response.status()).toBe(302);
      expect(response.headers().location).toContain('/panel');
    }
  }
});

test('RPT-04/06 AC-24 PDF multi-halaman dan pagination dengan EvaluationDataSeeder', async ({ ownerPage: owner }, info) => {
  test.skip(process.env.E2E_EVALUATION !== '1', 'Dijalankan terpisah dengan E2E_EVALUATION=1 agar fixture utama tidak berubah.');
  test.setTimeout(90_000);
  const year = new Date().getFullYear();
  await owner.goto(`/panel/reports?period_type=yearly&year=${year}`);
  const next = owner.getByRole('navigation', { name: 'Halaman detail transaksi', exact: true }).getByRole('link', { name: 'Berikutnya' });
  await expect(next).toBeVisible();
  const count = await owner.locator('article').filter({ has: owner.getByText('Transaksi lunas', { exact: true }) }).locator('p').nth(1).innerText();
  await next.click();
  await expect(owner.locator('article').filter({ has: owner.getByText('Transaksi lunas', { exact: true }) }).locator('p').nth(1)).toHaveText(count);
  const document = await pdf(owner, `/panel/reports/sales.pdf?period_type=yearly&year=${year}`);
  expect(document.pages).toBeGreaterThan(1);
  expect(document.pageTexts.filter((text) => /\bTRANSAKSI\b/i.test(text) && /\bWAKTU\b/i.test(text) && /\bKASIR\b/i.test(text)).length).toBeGreaterThan(1);
  expect(document.text).toMatch(/Halaman\s+\d+/i);
  await info.attach('evaluation-sales.pdf', { body: document.buffer, contentType: 'application/pdf' });
  writeFileSync(resolve(root, 'writable/e2e/evaluation-sales.pdf'), document.buffer);
  writeFileSync(resolve(root, 'writable/e2e/evaluation-pdf.json'), JSON.stringify({ transactions: count, pages: document.pages, repeatedHeaders: true, pageNumbers: true }, null, 2));
});
