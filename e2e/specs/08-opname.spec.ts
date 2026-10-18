import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { stockedProduct, opname, formAt, save, openShift, cart, pay } from '../helpers/data';
import { postWithCsrf } from '../helpers/http';
import { flash } from '../helpers/flash';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('OPN-01/06 AC-10 stok 10 fisik 8 diposting sekali dan tidak dapat diedit', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item } = await stockedProduct(owner, db, { direct: true, quantity: 10 });
  const session = await opname(cashier, db, item.id);
  await cashier.getByLabel(`Jumlah fisik ${item.name}`, { exact: true }).fill('8');
  await save(cashier, `/panel/opnames/${session.id}/counts`, /disimpan|tersimpan/i);
  await save(cashier, `/panel/opnames/${session.id}/submit`, /dikirim|diajukan|berhasil/i);
  await owner.goto('/panel/opnames');
  await save(owner, `/panel/opnames/${session.id}/approve`, /diposting|disetujui|berhasil/i);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(8);
  expect(await db.count('journal_entries', "source_type='stock_opname' AND source_id=?", [session.id])).toBe(1);
  const movements = await db.count('stock_movements');
  const journals = await db.count('journal_entries');
  await postWithCsrf(owner, `/panel/opnames/${session.id}/approve`);
  await owner.goto('/panel/opnames');
  await expect(owner.getByLabel(`Jumlah fisik ${item.name}`, { exact: true })).toHaveCount(0);
  expect(await db.count('stock_movements')).toBe(movements);
  expect(await db.count('journal_entries')).toBe(journals);
});

test('OPN-02 FR-05 owner meminta koreksi dan kasir mengajukan ulang', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item } = await stockedProduct(owner, db, { direct: true, quantity: 10 });
  const session = await opname(cashier, db, item.id);
  await cashier.getByLabel(`Jumlah fisik ${item.name}`, { exact: true }).fill('8');
  await save(cashier, `/panel/opnames/${session.id}/counts`, /disimpan|tersimpan/i);
  await save(cashier, `/panel/opnames/${session.id}/submit`, /dikirim|diajukan|berhasil/i);
  await owner.goto('/panel/opnames');
  await formAt(owner, `/panel/opnames/${session.id}/reject`).getByLabel('Catatan koreksi').fill('Hitung ulang barang yang tersisa');
  await save(owner, `/panel/opnames/${session.id}/reject`, /dikembalikan|koreksi|ditolak/i);
  expect((await db.one('SELECT status FROM stock_opnames WHERE id=?', [session.id])).status).toBe('rejected');
  await cashier.goto('/panel/opnames');
  await cashier.getByLabel(`Jumlah fisik ${item.name}`, { exact: true }).fill('10');
  await save(cashier, `/panel/opnames/${session.id}/counts`, /disimpan|tersimpan/i);
  await save(cashier, `/panel/opnames/${session.id}/submit`, /dikirim|diajukan|berhasil/i);
  expect((await db.one('SELECT status FROM stock_opnames WHERE id=?', [session.id])).status).toBe('submitted');
  await owner.goto('/panel/opnames');
  await save(owner, `/panel/opnames/${session.id}/approve`, /diposting|disetujui|berhasil/i);
});

test('OPN-03 FR-05 pembatalan membuka kunci dan POS dapat menjual lagi', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item, product } = await stockedProduct(owner, db, { direct: true, quantity: 2 });
  const session = await opname(cashier, db, item.id);
  const cancel = formAt(cashier, `/panel/opnames/${session.id}/cancel`);
  await cancel.locator('..').locator('summary').click();
  await cancel.getByLabel('Alasan pembatalan').fill('Batalkan sesi untuk pengujian POS');
  await save(cashier, `/panel/opnames/${session.id}/cancel`, /dibatalkan/);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(2);
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  await pay(cashier, db);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(1);
});
