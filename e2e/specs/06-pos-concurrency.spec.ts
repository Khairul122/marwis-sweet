import { test, expect } from '../fixtures';
import { spawn } from 'node:child_process';
import { openSync } from 'node:fs';
import { resolve } from 'node:path';
import { environment, root } from '../scripts/env.mjs';
import { Database } from '../helpers/db';
import { createCashier, stockedProduct, openShift, cart, save } from '../helpers/data';
import { login } from '../helpers/auth';
import { dismissFlash, flash, submit } from '../helpers/flash';
import { postWithCsrf } from '../helpers/http';
import { observe } from '../helpers/runtime';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('CON-01 AC-08 dua kasir memperebutkan satu stok lewat dua proses PHP', async ({ ownerPage: owner, cashierPage: first, browser, db }, info) => {
  test.setTimeout(90_000);
  const { item, product } = await stockedProduct(owner, db, { direct: true, quantity: 1 });
  const account = await createCashier(owner, db);
  const log = openSync(resolve(root, 'writable/e2e/concurrency-server.log'), 'a');
  const server = spawn(process.env.E2E_PHP ?? 'php', ['-S', 'localhost:8082', '-t', 'public', 'e2e/scripts/router.php'], {
    cwd: root, env: environment({ 'app.baseURL': 'http://localhost:8082/' }), stdio: ['ignore', log, log], windowsHide: true,
  });
  const context = await browser.newContext({ baseURL: 'http://localhost:8082/', viewport: { width: 1440, height: 1000 } });
  let verifySecond: (() => Promise<void>) | undefined;
  try {
    await expect.poll(async () => {
      try { return (await context.request.get('/login', { timeout: 1000 })).status(); } catch { return 0; }
    }, { timeout: 10_000 }).toBe(200);
    const second = await context.newPage();
    verifySecond = observe(second, info, 'cashier-second');
    await login(second, account.username, account.password);
    await expect(second).toHaveURL('http://localhost:8082/panel');
    await dismissFlash(second);
    await openShift(first, db);
    await openShift(second, db);
    await cart(first, product.variant_id);
    await cart(second, product.variant_id);
    const before = await db.count('sales');
    await Promise.all([first, second].map((page) => submit(page, page.locator('[data-pos-checkout]'), 'Bayar sekarang')));
    const successes = [first, second].filter((page) => /\/transactions\/\d+$/.test(page.url()));
    expect(successes).toHaveLength(1);
    const rejected = first === successes[0] ? second : first;
    await flash(rejected, /stok.*(cukup|habis)|persediaan/i);
    await expect(successes[0].locator('#receipt')).toContainText('Lunas');
    expect(await db.count('sales')).toBe(before + 1);
    expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(0);
    expect(await db.count('sale_items', 'product_variant_id=?', [product.variant_id])).toBe(1);
    await info.attach('concurrency.json', { body: JSON.stringify({ origins: ['localhost:8081', 'localhost:8082'], secondPhpPid: server.pid, successfulTransactions: 1 }), contentType: 'application/json' });
  } finally {
    try { await verifySecond?.(); }
    finally { await context.close(); server.kill(); }
  }
});

test('CON-02 FR-06 kasir tidak dapat membuka shift kedua', async ({ cashierPage: cashier, db }) => {
  await openShift(cashier, db);
  const before = await db.count('shifts', "status='open'");
  const response = await postWithCsrf(cashier, '/panel/shifts/open', { opening_cash: '100000' });
  expect(response.status()).toBe(303);
  await cashier.goto('/panel/shifts');
  await expect(cashier.locator('#flash-message')).toContainText(/aktif|sudah/i);
  expect(await db.count('shifts', "status='open'")).toBe(before);
});
