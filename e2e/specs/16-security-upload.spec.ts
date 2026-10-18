import { test, expect } from '../fixtures';
import { createCashier, formAt, save, unique } from '../helpers/data';
import { login } from '../helpers/auth';

test('SEC-01 PRD-10 POST tanpa CSRF ditolak tanpa perubahan data', async ({ ownerPage: page, db }) => {
  await page.goto('/panel/users');
  const before = await db.count('users');
  const response = await page.request.post('/panel/users/create', {
    form: { name: unique('CSRF'), username: unique('csrf'), password: 'E2ECashier!2026' }, maxRedirects: 0,
  });
  expect(response.status()).toBe(403);
  expect(await db.count('users')).toBe(before);
  await expect(page.getByRole('heading', { name: 'Akun kasir', exact: true })).toBeVisible();
});

test('SEC-02 PRD-10 bukti pengeluaran kasir lain tidak dapat dibuka', async ({ ownerPage: owner, cashierPage: cashier, guestPage: other, db }) => {
  const account = await createCashier(owner, db);
  await login(other, account.username, account.password);
  await other.goto('/panel/data/expense/create');
  await other.getByLabel('Kategori', { exact: true }).selectOption({ index: 1 });
  await other.getByLabel('Deskripsi', { exact: true }).fill(unique('Bukti privat'));
  await other.getByLabel('Nominal', { exact: true }).fill('10000');
  await other.getByLabel('Sumber pembayaran', { exact: true }).selectOption('main_cash');
  await other.getByLabel(/^Bukti \(/).setInputFiles({ name: 'bukti.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%%EOF') });
  await save(other, '/panel/expenses/create');
  const expense = (await db.rows('SELECT * FROM expenses WHERE created_by=? ORDER BY id DESC LIMIT 1', [account.id]))[0];
  expect(expense.evidence_path).toBeTruthy();
  const response = await cashier.goto(`/panel/expenses/${expense.id}/evidence`);
  expect(response!.status()).toBe(404);
  await expect(cashier.locator('body')).toContainText(/404|tidak ditemukan/i);
  expect(await db.count('expenses', 'id=?', [expense.id])).toBe(1);
});
