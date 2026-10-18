import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { createProduct, stockedProduct, openShift, cart, pay, opname, formAt, save, unique } from '../helpers/data';
import { flash, dismissFlash, submit } from '../helpers/flash';
import { postWithCsrf } from '../helpers/http';
import { pdf } from '../helpers/pdf';
import { money } from '../helpers/money';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('POS-01 AC-04 diskon Rp5000, tunai Rp50000, kembalian Rp5000', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none', price: 50000 });
  await openShift(cashier, db);
  const before = await db.count('sales');
  await cart(cashier, product.variant_id, { discount: 5000, cash: 50000, ownerApproval: true });
  expect(money(await cashier.locator('[data-pos-total]').innerText())).toBe(45000);
  const sale = await pay(cashier, db);
  expect(sale.total).toBe(45000);
  expect(await db.count('sales')).toBe(before + 1);
  const payment = await db.one('SELECT amount,cash_received,change_amount FROM payments WHERE sale_id=?', [sale.id]);
  expect(payment).toEqual({ amount: 45000, cash_received: 50000, change_amount: 5000 });
  await expect(cashier.locator('#receipt')).toContainText('Rp5.000');
});

test('POS-02 AC-05 tunai Rp20000 dan QRIS Rp25000 dihitung satu transaksi', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none', price: 45000 });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id, { split: 20000, cash: 20000 });
  const sale = await pay(cashier, db);
  expect(await db.rows('SELECT method,amount FROM payments WHERE sale_id=? ORDER BY id', [sale.id])).toEqual([
    { method: 'cash', amount: 20000 }, { method: 'qris', amount: 25000 },
  ]);
  expect(await db.count('sales', 'id=?', [sale.id])).toBe(1);
  await cashier.goto(`/panel/reports?product=${product.id}`);
  const total = cashier.locator('article').filter({ has: cashier.getByText('Transaksi lunas', { exact: true }) });
  await expect(total).toContainText('1');
  // AC-05 / BUG-E2E-003: transaksi gabungan benar, tetapi label konfirmasi manual tidak dirender.
  test.fail(true, 'BUG-E2E-003 POS-02: label dikonfirmasi manual tidak tersedia.');
  await expect(cashier.getByText(/dikonfirmasi manual/i)).toBeVisible();
});

test('POS-03 AC-06 dua porsi memakai 200 gram dan jurnal HPP sekali', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item, product } = await stockedProduct(owner, db);
  await openShift(cashier, db);
  const before = await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id]);
  await cart(cashier, product.variant_id, { quantity: 2 });
  const sale = await pay(cashier, db);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(before.quantity_on_hand - 200);
  expect(sale.total_cogs).toBe(2400);
  expect(await db.count('journal_entries', "source_type='sale' AND source_id=?", [sale.id])).toBe(1);
  const lines = await db.rows(`SELECT a.system_key,l.debit,l.credit FROM journal_lines l JOIN accounts a ON a.id=l.account_id
    JOIN journal_entries e ON e.id=l.journal_entry_id WHERE e.source_type='sale' AND e.source_id=?`, [sale.id]);
  expect(lines.filter((line) => line.system_key === 'cogs')).toEqual([expect.objectContaining({ debit: 2400 })]);
  expect(lines.filter((line) => line.system_key === 'sales_revenue' || line.system_key === 'sales')).toHaveLength(1);
});

test('POS-04 AC-07 klik bayar dua kali dan replay kunci idempotensi tidak menggandakan', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const before = await db.count('sales');
  const request = cashier.waitForRequest((request) => request.method() === 'POST' && request.url().endsWith('/panel/pos/finalize'));
  await cashier.locator('[data-pay-button]').scrollIntoViewIfNeeded();
  const box = await cashier.locator('[data-pay-button]').boundingBox();
  expect(box).not.toBeNull();
  await cashier.mouse.dblclick(box!.x + box!.width / 2, box!.y + box!.height / 2);
  const payload = (await request).postDataJSON();
  await expect(cashier).toHaveURL(/\/panel\/transactions\/\d+$/);
  await dismissFlash(cashier);
  const saleId = Number(cashier.url().split('/').pop());
  const replay = await postWithCsrf(cashier, '/panel/pos/finalize', payload);
  expect(replay.status()).toBe(303);
  expect(replay.headers().location).toBe(`http://localhost:8081/panel/transactions/${saleId}`);
  expect(await db.count('sales')).toBe(before + 1);
  expect(await db.count('sales', 'idempotency_key=?', [payload.idempotency_key])).toBe(1);
});

test('POS-05 AC-11 opname mengunci stok saat keranjang dibayar', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item, product } = await stockedProduct(owner, db);
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const session = await opname(owner, db, item.id);
  const before = await db.count('sales');
  try {
    await submit(cashier, cashier.locator('[data-pos-checkout]'), 'Bayar sekarang');
    await flash(cashier, /Stok .*sedang dikunci oleh opname/i);
    expect(await db.count('sales')).toBe(before);
    expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(2000);
  } finally {
    await owner.goto('/panel/opnames');
    const form = formAt(owner, `/panel/opnames/${session.id}/cancel`);
    await form.locator('..').locator('summary').click();
    await form.getByLabel('Alasan pembatalan').fill('Selesai menguji kunci stok');
    await save(owner, `/panel/opnames/${session.id}/cancel`, /dibatalkan|berhasil/i);
  }
});

test('POS-06 FR-06 diskon di atas batas memerlukan kredensial owner', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none', price: 50000 });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id, { discount: 10000 });
  await expect(cashier.locator('[data-discount-approval]')).toBeVisible();
  const before = await db.count('sales');
  await expect(cashier.getByRole('button', { name: 'Bayar sekarang' })).toBeDisabled();
  await expect(cashier.locator('[data-discount-approval]')).toContainText('Minta owner');
  expect(await db.count('sales')).toBe(before);
  await cashier.getByLabel('Username atau email owner', { exact: true }).fill('owner');
  await cashier.getByLabel('Kata sandi owner', { exact: true }).fill('DemoOwner!2026');
  expect((await pay(cashier, db)).total).toBe(40000);
});

test('POS-07 FR-06 keranjang ditahan, dilanjutkan, lalu dibayar', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const name = unique('Pelanggan');
  await cashier.getByLabel(/^Nama pelanggan/).fill(name);
  await submit(cashier, cashier.locator('[data-pos-checkout]'), 'Simpan sebagai pesanan ditahan');
  await flash(cashier, /Pesanan tersimpan/);
  const held = await db.one('SELECT * FROM pos_orders WHERE customer_name=?', [name]);
  expect(held.status).toBe('held');
  await cashier.goto(`/panel/pos?order=${held.id}`);
  await cashier.locator('[data-cash-received="1"]').fill('100000');
  await pay(cashier, db);
  expect((await db.one('SELECT status FROM pos_orders WHERE id=?', [held.id])).status).toBe('paid');
});

test('POS-08 FR-06 pembatalan keranjang held tidak mengubah stok atau jurnal', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const name = unique('Batal');
  await cashier.getByLabel(/^Nama pelanggan/).fill(name);
  await submit(cashier, cashier.locator('[data-pos-checkout]'), 'Simpan sebagai pesanan ditahan');
  await flash(cashier, /Pesanan tersimpan/);
  const held = await db.one('SELECT * FROM pos_orders WHERE customer_name=?', [name]);
  const before = await Promise.all(['sales', 'stock_movements', 'journal_entries'].map((table) => db.count(table)));
  await cashier.getByText(/Pesanan ditahan \(/).click();
  await formAt(cashier, `/panel/pos/orders/${held.id}/cancel`).getByRole('button', { name: 'Batalkan', exact: true }).click();
  await expect(cashier.locator('[data-confirm-dialog][open]').getByRole('button', { name: 'Batal', exact: true })).toBeFocused();
  await cashier.screenshot({ path: 'writable/e2e/bug004-dialog.png' });
  await cashier.locator('[data-confirm-dialog][open]').getByRole('button', { name: 'Lanjutkan' }).click();
  await expect.poll(async () => (await db.one('SELECT status FROM pos_orders WHERE id=?', [held.id])).status).toBe('cancelled');
  await flash(cashier, /Pesanan dibatalkan/);
  expect(await Promise.all(['sales', 'stock_movements', 'journal_entries'].map((table) => db.count(table)))).toEqual(before);
});

test('POS-08 fallback konfirmasi native menolak lalu menerima pembatalan held', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const name = unique('Fallback');
  await cashier.getByLabel(/^Nama pelanggan/).fill(name);
  await submit(cashier, cashier.locator('[data-pos-checkout]'), 'Simpan sebagai pesanan ditahan');
  await flash(cashier, /Pesanan tersimpan/);
  const held = await db.one('SELECT * FROM pos_orders WHERE customer_name=?', [name]);
  const before = await Promise.all(['sales', 'stock_movements', 'journal_entries'].map((table) => db.count(table)));
  await cashier.evaluate(() => Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { value: undefined, configurable: true }));
  await cashier.getByText(/Pesanan ditahan \(/).click();
  const cancel = formAt(cashier, `/panel/pos/orders/${held.id}/cancel`).getByRole('button', { name: 'Batalkan', exact: true });
  cashier.once('dialog', (dialog) => dialog.dismiss());
  await cancel.click();
  expect((await db.one('SELECT status FROM pos_orders WHERE id=?', [held.id])).status).toBe('held');
  cashier.once('dialog', (dialog) => dialog.accept());
  await cancel.click();
  await expect.poll(async () => (await db.one('SELECT status FROM pos_orders WHERE id=?', [held.id])).status).toBe('cancelled');
  await flash(cashier, /Pesanan dibatalkan/);
  expect(await Promise.all(['sales', 'stock_movements', 'journal_entries'].map((table) => db.count(table)))).toEqual(before);
});

test('POS-10 FR-06 pembayaran kurang ditolak', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id, { cash: 10000 });
  const before = await db.count('sales');
  await cashier.getByRole('button', { name: 'Bayar sekarang' }).click();
  await expect(cashier.locator('[data-pos-error]')).toContainText(/kurang|cukup/i);
  expect(await db.count('sales')).toBe(before);
});

test('POS-09 FR-06 topping, catatan tanpa kacang, dan meja tercetak pada struk', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item, product } = await stockedProduct(owner, db);
  const name = unique('Topping');
  await owner.goto('/panel/data/topping/create');
  await owner.getByLabel('Nama topping', { exact: true }).fill(name);
  await owner.getByLabel('SKU unik', { exact: true }).fill(unique('TOP').toUpperCase());
  await owner.getByLabel('Harga tambahan', { exact: true }).fill('2000');
  await save(owner, '/panel/master/toppings');
  const topping = await db.one('SELECT id FROM toppings WHERE name=?', [name]);
  await owner.goto(`/panel/data/topping/${topping.id}/edit`);
  await owner.getByLabel('Bahan', { exact: true }).selectOption(String(item.id));
  await owner.getByLabel('Jumlah', { exact: true }).fill('10');
  await save(owner, '/panel/master/toppings/items');
  await openShift(cashier, db);
  await cashier.goto('/panel/pos');
  const productCard = cashier.locator('[data-pos-product]').filter({ has: cashier.locator(`[data-add-product][data-variant-id="${product.variant_id}"]`) });
  await productCard.getByText('Tambah topping', { exact: true }).click();
  await productCard.locator(`[data-topping][value="${topping.id}"]`).check();
  await productCard.locator('[data-add-product]').click();
  if (await cashier.locator('[data-pos-cart-open]').isVisible()) await cashier.locator('[data-pos-cart-open]').click();
  await cashier.locator('[data-item-note]').fill('tanpa kacang');
  await cashier.getByLabel('Jenis pesanan', { exact: true }).selectOption('dine_in');
  await cashier.getByLabel(/^Nomor meja/).fill('12');
  await cashier.locator('[data-cash-received="1"]').fill('100000');
  const sale = await pay(cashier, db);
  expect(sale.total).toBe(27000);
  const line = await db.one('SELECT note,topping_snapshot FROM sale_items WHERE sale_id=?', [sale.id]);
  expect(line.note).toBe('tanpa kacang');
  expect(line.topping_snapshot).toContain(name);
  await expect(cashier.locator('#receipt')).toContainText(name);
  await expect(cashier.locator('#receipt')).toContainText('tanpa kacang');
  await expect(cashier.locator('#receipt')).toContainText('Makan di tempat');
  await expect(cashier.locator('#receipt')).toContainText('12');
});

test('POS-11 FR-06 struk HTML dan PDF memuat transaksi serta tautan ulasan', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const sale = await pay(cashier, db);
  await expect(cashier.locator('#receipt')).toContainText(sale.sale_number);
  await expect(cashier.locator('#receipt')).toContainText(product.name);
  await expect(cashier.locator('#receipt a[href*="/ulasan/"]')).toBeVisible();
  const document = await pdf(cashier, `/panel/transactions/${sale.id}.pdf`);
  expect(document.text).toContain(sale.sale_number);
  expect(document.text).toContain(product.name);
  expect(document.text).toContain('/ulasan/');
  expect((await db.one('SELECT total FROM sales WHERE id=?', [sale.id])).total).toBe(25000);
});

test.skip('POS-12 AC-09 rollback saat jurnal gagal dibuktikan melalui PHPUnit', async () => {});
