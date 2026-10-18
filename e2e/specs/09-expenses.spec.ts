import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { createExpense, accountBalance } from '../helpers/finance';
import { save, openShift, formAt } from '../helpers/data';
import { postWithCsrf } from '../helpers/http';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('EXP-01/02 AC-14 approval tidak membukukan, pembayaran membukukan sekali', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const expense = await createExpense(cashier, db);
  const balance = await accountBalance(db, 'main_cash');
  const journals = await db.count('journal_entries');
  await save(cashier, `/panel/expenses/${expense.id}/submit`, /dikirim|diajukan|berhasil/i);
  await owner.goto('/panel/expenses');
  await save(owner, `/panel/expenses/${expense.id}/approve`, /disetujui/);
  expect(await db.count('journal_entries')).toBe(journals);
  expect(await accountBalance(db, 'main_cash')).toBe(balance);
  await save(owner, `/panel/expenses/${expense.id}/pay`, /dibayar|pembayaran|diposting/i);
  expect(await db.count('journal_entries')).toBe(journals + 1);
  expect(await accountBalance(db, 'main_cash')).toBe(balance - 10000);
  expect((await db.one('SELECT status FROM expenses WHERE id=?', [expense.id])).status).toBe('paid');
  await postWithCsrf(owner, `/panel/expenses/${expense.id}/pay`);
  expect(await db.count('journal_entries')).toBe(journals + 1);
});

test('EXP-03 FR-07 pembayaran dari laci mencatat shift aktif', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const shift = await openShift(cashier, db);
  const expense = await createExpense(cashier, db, 'cash_drawer');
  expect(expense.shift_id).toBe(shift.id);
  await save(cashier, `/panel/expenses/${expense.id}/submit`, /dikirim|diajukan|berhasil/i);
  await owner.goto('/panel/expenses');
  await save(owner, `/panel/expenses/${expense.id}/approve`, /disetujui/);
  await save(owner, `/panel/expenses/${expense.id}/pay`, /dibayar|pembayaran|diposting/i);
  expect((await db.one('SELECT status FROM expenses WHERE id=?', [expense.id])).status).toBe('paid');
});

test('EXP-04 FR-07 nominal nol ditolak form', async ({ cashierPage: cashier, db }) => {
  await cashier.goto('/panel/data/expense/create');
  const before = await db.count('expenses');
  await cashier.getByLabel('Kategori', { exact: true }).selectOption({ index: 1 });
  await cashier.getByLabel('Deskripsi', { exact: true }).fill('Pengujian nominal nol');
  await cashier.getByLabel('Sumber pembayaran', { exact: true }).selectOption('main_cash');
  await cashier.getByLabel('Nominal', { exact: true }).fill('0');
  await formAt(cashier, '/panel/expenses/create').getByRole('button', { name: 'Simpan', exact: true }).click();
  expect(await cashier.getByLabel('Nominal', { exact: true }).evaluate((input: HTMLInputElement) => input.validity.rangeUnderflow)).toBe(true);
  expect(await db.count('expenses')).toBe(before);
});
