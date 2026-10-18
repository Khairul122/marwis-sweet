import { type Page, expect } from '@playwright/test';

export async function postWithCsrf(page: Page, path: string, data: Record<string, string> = {}) {
  const token = await page.locator('input[name=csrf_test_name]').first().inputValue();
  return page.request.post(path, { form: { ...data, csrf_test_name: token }, maxRedirects: 0 });
}

export async function roleDenied(page: Page, path: string) {
  const response = await page.goto(path);
  await expect(page).toHaveURL(/\/panel$/);
  await expect(page.locator('#flash-message')).toContainText(/akses|owner|kasir|pemilik/i);
  expect(await page.locator('body').innerText()).not.toMatch(/HPP|laba|buku besar|Jurnal umum/i);
  expect(await response!.text()).not.toMatch(/\bHPP\b|\blaba\b|buku besar|unit_cogs|total_cogs|gross_profit/i);
}
