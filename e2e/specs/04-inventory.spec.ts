import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { createItem, conversion, receiptDraft, postReceipt, formAt, filterTable, save, stockedProduct, unique } from '../helpers/data';
import { flash, submit } from '../helpers/flash';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('INV-01/02 AC-15 penerimaan 1 kg Rp12000 menambah persediaan tanpa beban', async ({ ownerPage: owner, db }) => {
  const item = await createItem(owner, db);
  await conversion(owner, item.id);
  const expenses = await db.one('SELECT COUNT(*) n, COALESCE(SUM(amount),0) amount FROM expenses');
  const receipt = await receiptDraft(owner, db, item.id, 1, 12000, 'kg');
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(0);
  await postReceipt(owner, owner, receipt.id);
  const balance = await db.one('SELECT quantity_on_hand,inventory_value FROM inventory_items WHERE id=?', [item.id]);
  expect(balance).toEqual({ quantity_on_hand: 1000, inventory_value: 12000 });
  expect(await db.one('SELECT COUNT(*) n, COALESCE(SUM(amount),0) amount FROM expenses')).toEqual(expenses);
  const lines = await db.rows(`SELECT a.system_key,l.debit,l.credit FROM journal_lines l JOIN accounts a ON a.id=l.account_id
    JOIN journal_entries e ON e.id=l.journal_entry_id WHERE e.source_type='goods_receipt' AND e.source_id=?`, [receipt.id]);
  expect(lines).toEqual(expect.arrayContaining([expect.objectContaining({ system_key: 'inventory', debit: 12000 }), expect.objectContaining({ credit: 12000 })]));
});

test('INV-06 FR-04 kartu stok memuat jenis, waktu, jumlah, dan referensi mutasi', async ({ ownerPage: owner, db }) => {
  const { item } = await stockedProduct(owner, db, { direct: true, quantity: 10 });
  await owner.goto('/panel/inventory');
  await filterTable(owner, 'Kartu stok terbaru', item.name);
  const row = owner.getByRole('row').filter({ hasText: item.name }).filter({ hasText: 'Penerimaan' });
  await expect(row).toContainText('10 pcs');
  const movement = await db.one('SELECT created_by,posted_at,quantity,notes FROM stock_movements WHERE inventory_item_id=?', [item.id]);
  expect(Number(movement.created_by)).toBeGreaterThan(0);
  expect(movement.posted_at).toBeTruthy();
  expect(movement.quantity).toBe(10);
  expect(movement.notes).toBeTruthy();
});

test('INV-07 FR-04 penyesuaian stok negatif ditolak sebelum persetujuan', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item } = await stockedProduct(owner, db, { direct: true, quantity: 1 });
  await cashier.goto('/panel/inventory/adjustments/new');
  await cashier.getByLabel('Jenis penyesuaian', { exact: true }).selectOption('correction');
  await cashier.getByLabel('Alasan', { exact: true }).fill('Pengujian stok tidak boleh negatif');
  await save(cashier, '/panel/inventory/adjustments', /dibuat/);
  const id = Number(cashier.url().split('/').pop());
  await cashier.getByLabel('Bahan', { exact: true }).selectOption(String(item.id));
  await cashier.getByLabel('Jumlah perubahan', { exact: true }).fill('-2');
  await save(cashier, '/panel/inventory/adjustments/items');
  const journals = await db.count('journal_entries');
  await submit(cashier, formAt(cashier, `/panel/inventory/adjustments/${id}/submit`));
  await flash(cashier, /pengurangan melebihi stok/);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(1);
  expect(await db.count('journal_entries')).toBe(journals);
});

test('INV-03 FR-04 kasir mengajukan penerimaan, owner approve, kasir posting', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const item = await createItem(owner, db);
  const receipt = await receiptDraft(cashier, db, item.id, 100, 10);
  expect((await db.one('SELECT status FROM goods_receipts WHERE id=?', [receipt.id])).status).toBe('draft');
  await save(cashier, `/panel/inventory/receipts/${receipt.id}/submit`, /diajukan|berhasil/i);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(0);
  await owner.goto(`/panel/inventory/receipts/${receipt.id}`);
  await save(owner, `/panel/inventory/receipts/${receipt.id}/approve`, /disetujui|berhasil/i);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(0);
  await cashier.goto(`/panel/inventory/receipts/${receipt.id}`);
  await save(cashier, '/panel/inventory/receipts/post', /diposting|berhasil/i);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(100);
});

test('INV-04 FR-04 penerimaan ditolak tidak menambah stok', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const item = await createItem(owner, db);
  const receipt = await receiptDraft(cashier, db, item.id);
  await save(cashier, `/panel/inventory/receipts/${receipt.id}/submit`, /diajukan|berhasil/i);
  await owner.goto(`/panel/inventory/receipts/${receipt.id}`);
  await owner.getByLabel('Kembalikan untuk perbaikan', { exact: true }).fill('Biaya perlu diperiksa ulang');
  await save(owner, `/panel/inventory/receipts/${receipt.id}/reject`, /dikembalikan|perbaikan|ditolak/i);
  const receiptRow = await db.one('SELECT status,review_notes FROM goods_receipts WHERE id=?', [receipt.id]);
  expect(receiptRow.status).toBe('rejected');
  expect(receiptRow.review_notes).toBe('Biaya perlu diperiksa ulang');
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(0);
});

test('INV-05 FR-04 penyesuaian disetujui dan pembalik mempertahankan mutasi asal', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const item = await createItem(owner, db, 'pcs');
  const receipt = await receiptDraft(owner, db, item.id, 10, 1000, 'pcs');
  await postReceipt(owner, owner, receipt.id);
  await cashier.goto('/panel/inventory/adjustments/new');
  await cashier.getByLabel('Jenis penyesuaian', { exact: true }).selectOption('damage');
  await cashier.getByLabel('Alasan', { exact: true }).fill(unique('Barang rusak'));
  await save(cashier, '/panel/inventory/adjustments', /dibuat|disimpan|berhasil/i);
  const id = Number(cashier.url().split('/').pop());
  await cashier.getByLabel('Bahan', { exact: true }).selectOption(String(item.id));
  await cashier.getByLabel('Jumlah perubahan', { exact: true }).fill('2');
  await save(cashier, '/panel/inventory/adjustments/items');
  await save(cashier, `/panel/inventory/adjustments/${id}/submit`, /diajukan|berhasil/i);
  await owner.goto(`/panel/inventory/adjustments/${id}`);
  await save(owner, `/panel/inventory/adjustments/${id}/approve`, /disetujui|berhasil/i);
  await cashier.goto(`/panel/inventory/adjustments/${id}`);
  await save(cashier, '/panel/inventory/adjustments/post', /diposting|berhasil/i);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(8);
  const original = await db.rows("SELECT * FROM stock_movements WHERE source_type='stock_adjustment' AND source_id=?", [id]);
  await owner.goto(`/panel/inventory/adjustments/${id}`);
  await owner.getByLabel('Alasan pembalik', { exact: true }).fill('Pembalik untuk pengujian audit');
  await save(owner, `/panel/inventory/adjustments/${id}/reverse`, /pembalik|dibalik|berhasil/i);
  expect((await db.one('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [item.id])).quantity_on_hand).toBe(10);
  expect(await db.rows("SELECT * FROM stock_movements WHERE source_type='stock_adjustment' AND source_id=?", [id])).toEqual(original);
  expect(await db.count('stock_adjustments', 'reversal_of_id=?', [id])).toBe(1);
});
