import { test, expect } from '../fixtures';
import { createCashier, filterTable, idForm, save } from '../helpers/data';
import { login } from '../helpers/auth';
import { dismissFlash, submit, flash } from '../helpers/flash';

test('USR-01 FR-01 owner membuat kasir, mereset password, dan menonaktifkan', async ({ ownerPage: owner, guestPage: cashier, db }) => {
  const account = await createCashier(owner, db);
  await owner.goto(`/panel/data/password/${account.id}/edit`);
  await owner.getByLabel(/^Password baru/).fill('E2EPasswordBaru!2026');
  await save(owner, '/panel/users/reset-password', /berhasil direset/);
  await login(cashier, account.username, 'E2EPasswordBaru!2026');
  await expect(cashier).toHaveURL(/\/panel$/);
  await dismissFlash(cashier);
  await cashier.getByRole('button', { name: 'Keluar', exact: true }).click();
  await owner.goto('/panel/users');
  await filterTable(owner, 'Semua data akun kasir', account.username);
  await submit(owner, idForm(owner, '/panel/users/status', 'user_id', account.id));
  await flash(owner, /dinonaktifkan/);
  await login(cashier, account.username, 'E2EPasswordBaru!2026');
  await expect(cashier).toHaveURL(/\/login$/);
  await expect(cashier.getByRole('alert')).toContainText('Username/email atau password tidak sesuai.');
  expect((await db.one('SELECT status FROM users WHERE id=?', [account.id])).status).toBe('inactive');
});

test('USR-02 FR-01 username dan email duplikat ditolak', async ({ ownerPage: owner, db }) => {
  const before = await db.count('users');
  await owner.goto('/panel/data/cashier/create');
  await owner.getByLabel('Nama lengkap', { exact: true }).fill('Kasir duplikat E2E');
  await owner.getByLabel('Username', { exact: true }).fill('kasir');
  await owner.getByLabel(/^Password awal/).fill('E2ECashier!2026');
  await submit(owner, owner.locator('form[action$="/panel/users/create"]'));
  await expect(owner.getByText(/sudah|unik|unique/i).first()).toBeVisible();
  expect(await db.count('users')).toBe(before);
  await owner.getByLabel('Username', { exact: true }).fill(`E2E-kasir-${Date.now()}`);
  await owner.getByLabel(/^Email/).fill('kasir.demo@localhost.test');
  await owner.getByLabel(/^Password awal/).fill('E2ECashier!2026');
  await submit(owner, owner.locator('form[action$="/panel/users/create"]'));
  await expect(owner.getByText(/sudah|unik|unique/i).first()).toBeVisible();
  expect(await db.count('users')).toBe(before);
});

test('AUD-01 FR-08 audit tampil dan tidak menyimpan password atau token mentah', async ({ ownerPage: owner, db }) => {
  const account = await createCashier(owner, db);
  await owner.goto('/panel/audit');
  await expect(owner.getByRole('heading', { name: 'Riwayat perubahan', exact: true })).toBeVisible();
  await expect(owner.getByRole('list', { name: 'Daftar riwayat perubahan' })).toBeVisible();
  const rows = await db.rows('SELECT event_type,before_values,after_values,reason FROM audit_events');
  expect(rows.length).toBeGreaterThan(0);
  const json = JSON.stringify(rows);
  for (const secret of ['DemoOwner!2026', 'DemoKasir!2026', account.password, 'E2EPasswordBaru!2026']) expect(json).not.toContain(secret);
  expect(json).not.toMatch(/"(?:password|password_hash|encrypted_token|token_hash)"\s*:\s*"(?!\[)/i);
});
