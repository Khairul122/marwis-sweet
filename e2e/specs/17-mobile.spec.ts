import { test, expect } from '../fixtures';
import { createProduct, openShift, cart, pay } from '../helpers/data';
import { reviewSale } from '../helpers/reviews';
import { authenticated } from '../helpers/auth';
import { Database } from '../helpers/db';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('MOB-01 FR-02 @mobile publik, detail, dan form ulasan tanpa scroll horizontal pada 390px', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }, info) => {
  const { product, link } = await reviewSale(owner, cashier, db);
  for (const [name, path] of [['home', '/'], ['product', `/menu/${product.slug}`], ['review', link]]) {
    await guest.goto(path);
    await expect(guest.locator('main h1')).toBeVisible();
    const dimensions = await guest.evaluate(() => ({ width: innerWidth, content: document.documentElement.scrollWidth }));
    expect(dimensions.width).toBe(390);
    expect(dimensions.content, name).toBeLessThanOrEqual(dimensions.width);
    await info.attach(`mobile-${name}.png`, { body: await guest.screenshot({ fullPage: true }), contentType: 'image/png' });
  }
});

test('MOB-02 FR-06 @mobile kasir menambah produk dan membayar pada 390px', async ({ ownerPage: owner, guestPage: cashier, db }, info) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await authenticated(cashier, 'cashier');
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  await expect(cashier.getByRole('complementary', { name: 'Keranjang dan pembayaran' })).toBeVisible();
  await info.attach('mobile-pos.png', { body: await cashier.screenshot(), contentType: 'image/png' });
  const sale = await pay(cashier, db);
  expect(sale.total).toBe(25000);
  expect(await db.count('sale_items', 'sale_id=?', [sale.id])).toBe(1);
});
