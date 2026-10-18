import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { createCategory, createItem, createProduct, conversion, stockedProduct, openShift, cart, pay, recipe, formAt, idForm, filterTable, save, unique } from '../helpers/data';
import { flash, submit } from '../helpers/flash';
import { productPhoto } from '../helpers/uploads';

test.afterAll(async () => { const db = new Database(); try { await db.assertJournalsBalanced(); } finally { await db.close(); } });

test('CAT-01 FR-03 owner membuat kategori, produk, resep, varian, dan topping', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
  const category = await createCategory(owner, db);
  const item = await createItem(owner, db);
  const product = await createProduct(owner, db, { categoryId: category.id, itemId: item.id });
  await expect(owner.locator('body')).toContainText(item.name);
  expect((await db.one('SELECT quantity FROM recipe_items WHERE inventory_item_id=?', [item.id])).quantity).toBe(100);
  await owner.goto(`/panel/data/variant/create?product_id=${product.id}`);
  await owner.getByLabel('Nama varian', { exact: true }).fill('Besar');
  await owner.getByLabel('SKU unik', { exact: true }).fill(unique('VARIAN').toUpperCase());
  await owner.getByLabel('Harga jual', { exact: true }).fill('35000');
  await owner.getByLabel('Pengurangan stok', { exact: true }).selectOption('none');
  await save(owner, '/panel/catalog/variants');
  await owner.goto('/panel/data/topping/create');
  const topping = unique('Topping');
  await owner.getByLabel('Nama topping', { exact: true }).fill(topping);
  await owner.getByLabel('SKU unik', { exact: true }).fill(unique('TOP').toUpperCase());
  await owner.getByLabel('Harga tambahan', { exact: true }).fill('2000');
  await save(owner, '/panel/master/toppings');
  await guest.goto(`/menu/${product.slug}`);
  await expect(guest.getByRole('heading', { name: product.name, exact: true })).toBeVisible();
  await expect(guest.locator('body')).toContainText('Besar');
  await openShift(cashier, db);
  await cashier.goto('/panel/pos');
  await expect(cashier.locator(`[data-add-product][data-variant-id="${product.variant_id}"]`)).toBeVisible();
  expect(await db.count('toppings', 'name=?', [topping])).toBe(1);
});

test('CAT-05 FR-03 maksimum lima foto produk pada UI', async ({ ownerPage: owner, db }) => {
  test.setTimeout(90_000);
  const product = await createProduct(owner, db, { stockMode: 'none' });
  for (let index = 0; index < 5; index++) await productPhoto(owner, product.id);
  await expect(owner.getByRole('heading', { name: 'Foto produk (5/5)', exact: true })).toBeVisible();
  await expect(formAt(owner, '/panel/catalog/products/images')).toHaveCount(0);
  expect(await db.count('product_images', 'product_id=?', [product.id])).toBe(5);
});

test('CAT-02 FR-03 konversi satu kilogram menjadi 1000 gram', async ({ ownerPage: owner, db }) => {
  const item = await createItem(owner, db);
  await conversion(owner, item.id);
  await expect(owner.getByRole('cell', { name: 'kg', exact: true })).toBeVisible();
  expect((await db.one('SELECT factor_to_base FROM unit_conversions WHERE inventory_item_id=? AND purchase_unit=?', [item.id, 'kg'])).factor_to_base).toBe(1000);
});

test('CAT-03 AC-03 harga dan HPP lama tetap setelah perubahan harga dan resep', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { item, product } = await stockedProduct(owner, db);
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const first = await pay(cashier, db);
  const snapshot = await db.one('SELECT * FROM sale_items WHERE sale_id=?', [first.id]);
  await owner.goto(`/panel/data/variant/${product.variant_id}/edit`);
  await owner.getByLabel('Harga jual', { exact: true }).fill('30000');
  await save(owner, '/panel/catalog/variants/update');
  await recipe(owner, product.variant_id, item.id, 200);
  await cart(cashier, product.variant_id);
  const second = await pay(cashier, db);
  const current = await db.one('SELECT * FROM sale_items WHERE sale_id=?', [second.id]);
  expect(current.unit_price).toBe(30000);
  expect(current.line_cogs).toBe(2400);
  expect(await db.one('SELECT * FROM sale_items WHERE sale_id=?', [first.id])).toEqual(snapshot);
  await cashier.goto(`/panel/transactions/${first.id}`);
  await expect(cashier.locator('#receipt')).toContainText('Rp25.000');
});

test('CAT-04 FR-03 produk terpakai transaksi tidak dapat dihapus', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id);
  const sale = await pay(cashier, db);
  expect(await db.count('sale_items', 'sale_id=?', [sale.id])).toBe(1);
  await owner.goto('/panel/catalog');
  await filterTable(owner, 'Semua data produk', product.name);
  await submit(owner, idForm(owner, '/panel/catalog/products/delete', 'product_id', product.id));
  expect(await db.count('products', 'id=?', [product.id])).toBe(1);
  expect(await db.count('sale_items', 'sale_id=?', [sale.id])).toBe(1);
  await flash(owner, /transaksi|digunakan|nonaktif/i);
});
