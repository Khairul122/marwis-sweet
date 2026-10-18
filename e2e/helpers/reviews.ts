import { type Page, expect } from '@playwright/test';
import { Database } from './db';
import { createProduct, openShift, cart, pay } from './data';

export async function reviewSale(owner: Page, cashier: Page, db: Database) {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const sale = await pay(cashier, db);
  const link = await cashier.locator('#receipt a[href*="/ulasan/"]').getAttribute('href');
  expect(link).toMatch(/^http:\/\/localhost:8081\/ulasan\/[a-f0-9]{64}$/);
  const item = await db.one('SELECT id FROM sale_items WHERE sale_id=?', [sale.id]);
  return { product, sale, item, link: link! };
}

export async function fillReview(page: Page, itemId: number, comment: string) {
  await page.getByLabel('Produk', { exact: true }).selectOption(String(itemId));
  await page.locator('label').filter({ has: page.getByRole('radio', { name: '5 dari 5', exact: true }) }).click();
  await page.getByLabel('Komentar', { exact: true }).fill(comment);
  await page.getByLabel(/^Saya memahami/).check();
  const captcha = page.getByLabel(/^Verifikasi:/);
  if (await captcha.count()) {
    const label = await page.locator('label[for="captcha-answer"]').innerText();
    const numbers = label.match(/\d+/g)!.map(Number);
    await captcha.fill(String(numbers[0] + numbers[1]));
  }
}
