import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { stockedProduct, openShift, cart, pay, formAt, save } from '../helpers/data';
import { requestRefund, approveRefund } from '../helpers/finance';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('REF-01/05 AC-12 refund penuh produk resep membalik uang tanpa mengembalikan tepung', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item, product } = await stockedProduct(owner, db);
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const sale = await pay(cashier, db);
  const before = await db.one('SELECT quantity_on_hand,inventory_value FROM inventory_items WHERE id=?', [item.id]);
  const refund = await requestRefund(cashier, db, sale.id);
  expect(refund.status).toBe('submitted');
  expect(await db.count('journal_entries', "source_type='refund' AND source_id=?", [refund.id])).toBe(0);
  await approveRefund(owner, sale.id, refund.id);
  expect(await db.one('SELECT quantity_on_hand,inventory_value FROM inventory_items WHERE id=?', [item.id])).toEqual(before);
  expect((await db.one('SELECT status FROM sales WHERE id=?', [sale.id])).status).toBe('refunded');
  expect((await db.one('SELECT amount,status FROM refunds WHERE id=?', [refund.id]))).toEqual({ amount: 25000, status: 'posted' });
  expect(await db.count('review_tokens', 'sale_id=? AND disabled_at IS NULL', [sale.id])).toBe(0);
  const cash = await db.one(`SELECT SUM(l.credit-l.debit) amount FROM journal_lines l JOIN accounts a ON a.id=l.account_id
    JOIN journal_entries e ON e.id=l.journal_entry_id WHERE e.source_type='refund' AND e.source_id=? AND a.system_key='cash_drawer'`, [refund.id]);
  expect(cash.amount).toBe(25000);
});

test('REF-02 AC-13 barang layak jual kembali dengan biaya transaksi asal', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item, product } = await stockedProduct(owner, db, { direct: true, quantity: 2 });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const sale = await pay(cashier, db);
  const before = await db.one('SELECT quantity_on_hand,inventory_value FROM inventory_items WHERE id=?', [item.id]);
  const refund = await requestRefund(cashier, db, sale.id, 1, true);
  await approveRefund(owner, sale.id, refund.id);
  const after = await db.one('SELECT quantity_on_hand,inventory_value FROM inventory_items WHERE id=?', [item.id]);
  expect(after.quantity_on_hand).toBe(before.quantity_on_hand + 1);
  expect(after.inventory_value).toBe(before.inventory_value + 4000);
  const cogs = await db.one(`SELECT SUM(l.credit) amount FROM journal_lines l JOIN accounts a ON a.id=l.account_id
    JOIN journal_entries e ON e.id=l.journal_entry_id WHERE e.source_type='refund' AND e.source_id=? AND a.system_key='cogs'`, [refund.id]);
  expect(cogs.amount).toBe(4000);
});

test('REF-03 AC-12 kuantitas refund melebihi pembelian ditolak di form', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { product } = await stockedProduct(owner, db);
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const sale = await pay(cashier, db);
  const form = formAt(cashier, `/panel/transactions/${sale.id}/refunds`);
  const quantity = form.getByLabel(/^Jumlah refund/);
  await quantity.fill('2');
  await form.getByLabel('Alasan refund', { exact: true }).fill('Jumlah melebihi pembelian');
  await form.getByRole('button', { name: 'Kirim pengajuan' }).click();
  expect(await quantity.evaluate((input: HTMLInputElement) => input.validity.rangeOverflow)).toBe(true);
  await expect(cashier).toHaveURL(new RegExp(`/transactions/${sale.id}$`));
  expect(await db.count('refunds', 'sale_id=?', [sale.id])).toBe(0);
});

test('REF-04/06 FR-11 refund ditolak tanpa jurnal, kemudian refund sebagian', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { product } = await stockedProduct(owner, db);
  await openShift(cashier, db);
  await cart(cashier, product.variant_id, { quantity: 2 });
  const sale = await pay(cashier, db);
  const rejected = await requestRefund(cashier, db, sale.id);
  await owner.goto(`/panel/transactions/${sale.id}`);
  await formAt(owner, `/panel/refunds/${rejected.id}/reject`).getByLabel('Alasan penolakan').fill('Barang sesuai pesanan pelanggan');
  await save(owner, `/panel/refunds/${rejected.id}/reject`, /ditolak/);
  expect(await db.count('journal_entries', "source_type='refund' AND source_id=?", [rejected.id])).toBe(0);
  const approved = await requestRefund(cashier, db, sale.id);
  await approveRefund(owner, sale.id, approved.id);
  expect((await db.one('SELECT status FROM sales WHERE id=?', [sale.id])).status).toBe('partially_refunded');
});
