import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { fillJournal, accountBalance } from '../helpers/finance';
import { save, formAt, unique } from '../helpers/data';
import { submit, flash } from '../helpers/flash';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('ACC-01 FR-08 jurnal manual menolak akun debit dan kredit yang sama', async ({ ownerPage: owner, db }) => {
  await owner.goto('/panel/accounting');
  const before = await db.count('journal_entries');
  await fillJournal(owner, db, { sameAccount: true });
  await submit(owner, formAt(owner, '/panel/accounting/journals'));
  await flash(owner, /tidak valid/);
  expect(await db.count('journal_entries')).toBe(before);
});

test('ACC-02 FR-08 jurnal seimbang dengan alasan dan audit', async ({ ownerPage: owner, db }) => {
  await owner.goto('/panel/accounting');
  const description = await fillJournal(owner, db);
  await save(owner, '/panel/accounting/journals', /berhasil diposting/);
  const entry = await db.one("SELECT * FROM journal_entries WHERE description=? AND source_type='manual_journal'", [description]);
  const totals = await db.one('SELECT SUM(debit) debit,SUM(credit) credit FROM journal_lines WHERE journal_entry_id=?', [entry.id]);
  expect(totals).toEqual({ debit: 10000, credit: 10000 });
  expect(await db.count('audit_events', "event_type='accounting.journal.manual_posted' AND object_id=?", [String(entry.id)])).toBe(1);
});

test('ACC-03/04 AC-22 periode tertutup menolak posting dan dapat dibuka dengan alasan', async ({ ownerPage: owner, db }) => {
  const month = (await db.one("SELECT DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m') month")).month;
  await owner.goto('/panel/accounting');
  const close = formAt(owner, '/panel/accounting/periods/close');
  await close.getByLabel('Bulan', { exact: true }).fill(month);
  await close.getByLabel('Alasan', { exact: true }).fill('Tutup periode untuk pengujian E2E');
  await save(owner, '/panel/accounting/periods/close', /ditutup/);
  const before = await db.count('journal_entries');
  await fillJournal(owner, db, { date: `${month}-15` });
  await submit(owner, formAt(owner, '/panel/accounting/journals'));
  await flash(owner, /ditutup|tertutup/i);
  expect(await db.count('journal_entries')).toBe(before);
  const period = await db.one('SELECT id FROM accounting_periods WHERE period_start=?', [`${month}-01`]);
  const reopen = formAt(owner, `/panel/accounting/periods/${period.id}/reopen`);
  await reopen.getByLabel('Alasan pembukaan').fill('Buka kembali setelah verifikasi penolakan');
  await save(owner, `/panel/accounting/periods/${period.id}/reopen`, /dibuka/);
  expect((await db.one('SELECT status FROM accounting_periods WHERE id=?', [period.id])).status).toBe('open');
});

test('ACC-05 FR-08 saldo awal selalu seimbang', async ({ ownerPage: owner, db }) => {
  await owner.goto('/panel/accounting');
  const form = formAt(owner, '/panel/accounting/opening-balances');
  await form.locator('..').locator('summary').click();
  await form.getByLabel('Nominal', { exact: true }).fill('100000');
  await save(owner, '/panel/accounting/opening-balances', /diposting|berhasil/i);
  await db.assertJournalsBalanced();
  expect(await db.count('journal_entries', "source_type='opening_balance'")).toBeGreaterThan(0);
});

test('ACC-06 FR-08 rekonsiliasi seluruh jurnal dan nilai akun persediaan', async ({ ownerPage: owner, db }) => {
  await owner.goto('/panel/accounting');
  await expect(owner.locator('#ringkasan')).toContainText('Seimbang');
  await db.assertJournalsBalanced();
  const stock = await db.one('SELECT COALESCE(SUM(inventory_value),0) value FROM inventory_items');
  expect(await accountBalance(db, 'inventory')).toBe(stock.value);
});
