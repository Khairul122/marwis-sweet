import { test, expect } from '../fixtures';
import { login } from '../helpers/auth';
import { dismissFlash, flash, submit } from '../helpers/flash';
import { clearThrottle } from '../scripts/env.mjs';
import { postWithCsrf, roleDenied } from '../helpers/http';
import { pdf } from '../helpers/pdf';
import { createCashier, formAt, save } from '../helpers/data';

test('AUTH-01 FR-01 owner masuk melalui username dan email', async ({ guestPage: page }) => {
  for (const identity of ['owner', 'owner.demo@localhost.test']) {
    await login(page, identity);
    await expect(page).toHaveURL(/\/panel$/);
    await dismissFlash(page);
    for (const name of ['Pembukuan', 'Akun kasir', 'Riwayat perubahan']) {
      await expect(page.getByRole('link', { name, exact: true })).toBeVisible();
    }
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
  }
});

test('AUTH-02 FR-01 kasir memiliki navigasi sesuai role', async ({ cashierPage: page }) => {
  for (const name of ['Pembukuan', 'Akun kasir', 'Riwayat perubahan', 'Produk dan kategori']) {
    await expect(page.getByRole('link', { name, exact: true })).toHaveCount(0);
  }
  await expect(page.getByRole('link', { name: 'POS', exact: true })).toBeVisible();
});

test('AUTH-06 AC-01 tamu ditolak pada panel, POS, dan PDF', async ({ guestPage: page }) => {
  for (const path of ['/panel', '/panel/pos', '/panel/reports/sales.pdf']) {
    await page.goto(path);
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { name: 'Masuk ke akun' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Navigasi utama' })).toHaveCount(0);
  }
});

test('AUTH-03 FR-01 password salah tidak mengungkap keberadaan akun', async ({ guestPage: page }) => {
  for (const identity of ['owner', 'akun-tidak-ada-e2e']) {
    await login(page, identity, 'PasswordSalah!2026');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('alert')).toHaveText('Username/email atau password tidak sesuai.');
  }
  clearThrottle();
});

test('AUTH-04 FR-01 percobaan keenam dibatasi meskipun password benar', async ({ guestPage: page }) => {
  try {
    clearThrottle();
    for (let attempt = 0; attempt < 5; attempt++) await login(page, 'owner', 'PasswordSalah!2026');
    await login(page);
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('alert')).toHaveText('Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
  } finally { clearThrottle(); }
});

test('AUTH-05 FR-01 logout, Back, dan akses ulang panel', async ({ ownerPage: page }) => {
  await page.getByRole('button', { name: 'Keluar', exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.goBack();
  await page.goto('/panel');
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Masuk ke akun' })).toBeVisible();
});

test('AUTH-07 AC-02 seluruh grup owner menolak URL langsung kasir', async ({ cashierPage: page }) => {
  for (const path of ['accounting', 'users', 'audit', 'catalog', 'master', 'settings', 'shift-reviews', 'reviews']) {
    await roleDenied(page, `/panel/${path}`);
    await dismissFlash(page);
  }
});

test('AUTH-08 AC-02 POST dengan CSRF sah tetap ditolak untuk role kasir', async ({ cashierPage: page, db }) => {
  const before = await Promise.all(['journal_entries', 'refunds', 'expenses'].map((table) => db.count(table)));
  for (const path of ['/panel/accounting/journals', '/panel/refunds/999999/approve', '/panel/expenses/999999/pay']) {
    await page.goto('/panel');
    const response = await postWithCsrf(page, path, { amount: '10000' });
    expect(response.status()).toBe(303);
    expect(response.headers().location).toMatch(/\/panel$/);
    await page.goto(response.headers().location);
    await expect(page.locator('#flash-message')).toContainText('owner');
    expect(await page.locator('body').innerText()).not.toMatch(/HPP|laba|buku besar/i);
  }
  expect(await Promise.all(['journal_entries', 'refunds', 'expenses'].map((table) => db.count(table)))).toEqual(before);
});

test('AUTH-09 AC-02 PDF kasir tanpa HPP, laba, atau buku besar', async ({ cashierPage: page }) => {
  await page.goto('/panel/reports');
  await expect(page.getByRole('heading', { name: /Laporan penjualan/ }).first()).toBeVisible();
  const report = await pdf(page, '/panel/reports/sales.pdf');
  expect(report.text).not.toMatch(/HPP|laba|buku besar/i);
});

test('AUTH-10 FR-01 POS dan shift khusus kasir menolak owner', async ({ ownerPage: page }) => {
  for (const path of ['/panel/pos', '/panel/shifts']) {
    await page.goto(path);
    await expect(page).toHaveURL(/\/panel$/);
    await expect(page.locator('#flash-message')).toContainText('kasir');
  }
});

test('AUTH-11 FR-01 akun dinonaktifkan mengakhiri sesi kasir aktif', async ({ ownerPage: owner, guestPage: cashier, db }) => {
  const account = await createCashier(owner, db);
  await login(cashier, account.username, account.password);
  await expect(cashier).toHaveURL(/\/panel$/);
  await owner.goto('/panel/users');
  await submit(owner, owner.locator('tr').filter({ hasText: account.username }).locator('form[action$="/panel/users/status"]'));
  await flash(owner, /dinonaktifkan|berhasil|diperbarui/i);
  await cashier.goto('/panel');
  await expect(cashier).toHaveURL(/\/login$/);
});

test('AUTH-12 FR-01 login mengembalikan tamu ke laporan yang dituju', async ({ guestPage: page }) => {
  // FR-01 / BUG-E2E-001: login mengabaikan tujuan awal dan selalu menuju dashboard.
  test.fail(true, 'BUG-E2E-001 AUTH-12: tujuan laporan tidak dipulihkan setelah login.');
  await page.goto('/panel/reports');
  await page.getByLabel('Username atau email', { exact: true }).fill('owner');
  await page.getByLabel('Password', { exact: true }).fill('DemoOwner!2026');
  await page.getByRole('button', { name: 'Masuk ke panel' }).click();
  await expect(page).toHaveURL(/\/panel\/reports$/);
});
