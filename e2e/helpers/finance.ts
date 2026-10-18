import { type Page, expect } from '@playwright/test';
import { Database } from './db';
import { formAt, save, unique } from './data';

export async function requestRefund(cashier: Page, db: Database, saleId: number, quantity = 1, restock = false) {
  await cashier.goto(`/panel/transactions/${saleId}`);
  const form = formAt(cashier, `/panel/transactions/${saleId}/refunds`);
  await form.getByLabel(/^Jumlah refund/).fill(String(quantity));
  if (restock) await form.getByLabel('Barang kembali dan layak dijual', { exact: true }).check();
  await form.getByLabel('Alasan refund', { exact: true }).fill(unique('Refund pengujian'));
  await save(cashier, `/panel/transactions/${saleId}/refunds`, /Pengajuan refund dikirim/);
  return (await db.rows('SELECT * FROM refunds WHERE sale_id=? ORDER BY id DESC LIMIT 1', [saleId]))[0];
}

export async function approveRefund(owner: Page, saleId: number, refundId: number) {
  await owner.goto(`/panel/transactions/${saleId}`);
  await save(owner, `/panel/refunds/${refundId}/approve`, /Refund disetujui dan dibukukan/);
}

export async function createExpense(cashier: Page, db: Database, source = 'main_cash') {
  const description = unique('Pengeluaran');
  await cashier.goto('/panel/data/expense/create');
  await cashier.getByLabel('Kategori', { exact: true }).selectOption({ index: 1 });
  await cashier.getByLabel('Deskripsi', { exact: true }).fill(description);
  await cashier.getByLabel('Nominal', { exact: true }).fill('10000');
  await cashier.getByLabel('Sumber pembayaran', { exact: true }).selectOption(source);
  await save(cashier, '/panel/expenses/create');
  return db.one('SELECT * FROM expenses WHERE description=?', [description]);
}

export async function accountBalance(db: Database, key: string) {
  return Number((await db.one(`SELECT COALESCE(SUM(l.debit-l.credit),0) balance FROM accounts a
    LEFT JOIN journal_lines l ON l.account_id=a.id LEFT JOIN journal_entries e ON e.id=l.journal_entry_id
    WHERE a.system_key=? AND (e.status='posted' OR e.id IS NULL)`, [key])).balance);
}

export async function fillJournal(owner: Page, db: Database, options: { date?: string; sameAccount?: boolean } = {}) {
  const accounts = await db.rows('SELECT id FROM accounts WHERE allow_manual=1 AND is_active=1 ORDER BY id LIMIT 2');
  const form = formAt(owner, '/panel/accounting/journals');
  await form.getByLabel('Akun debit', { exact: true }).selectOption(String(accounts[0].id));
  await form.getByLabel('Akun kredit', { exact: true }).selectOption(String(accounts[options.sameAccount ? 0 : 1].id));
  await form.getByLabel('Nominal', { exact: true }).fill('10000');
  const description = unique('Jurnal manual');
  await form.getByLabel('Keterangan', { exact: true }).fill(description);
  if (options.date) await form.getByLabel('Tanggal', { exact: true }).fill(options.date);
  return description;
}
