import { expect, type Page } from '@playwright/test';
import { dismissFlash } from './flash';

export async function login(page: Page, identity = 'owner', password = 'DemoOwner!2026') {
  await page.goto('/login');
  await page.getByLabel('Username atau email', { exact: true }).fill(identity);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load' }),
    page.getByRole('button', { name: 'Masuk ke panel' }).click(),
  ]);
}

export async function authenticated(page: Page, role: 'owner' | 'cashier') {
  await login(page, role === 'owner' ? 'owner' : 'kasir', role === 'owner' ? 'DemoOwner!2026' : 'DemoKasir!2026');
  await expect(page).toHaveURL(/\/panel$/);
  await dismissFlash(page);
}
