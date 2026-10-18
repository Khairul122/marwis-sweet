import { test, expect } from '../fixtures';
import { createCategory, createProduct, filterTable, idForm, openShift, save } from '../helpers/data';
import { submit, flash } from '../helpers/flash';
import { productPhoto } from '../helpers/uploads';

test('PUB-01/05 FR-02 informasi publik dan harga tanpa stok angka atau biaya internal', async ({ guestPage: page, db }, info) => {
  const response = await page.goto('/');
  expect(response!.status()).toBe(200);
  const settings = await db.rows("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('business_name','opening_hours','address','phone')");
  for (const row of settings) await expect(page.locator('body')).toContainText(row.setting_value);
  await expect(page.locator('[data-product-card]').first()).toBeVisible();
  await expect(page.locator('body')).toContainText(/Rp\s*[\d.]+/);
  expect(await response!.text()).not.toMatch(/quantity_on_hand|inventory_value|average_cost|unit_cogs|\bHPP\b|biaya produksi/i);
  await expect(page.getByRole('button', { name: /checkout|bayar sekarang/i })).toHaveCount(0);
  await info.attach('public-desktop.png', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
});

test('PUB-02 FR-02 detail memuat foto, varian, harga, dan status ulasan', async ({ ownerPage: owner, guestPage: page, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await productPhoto(owner, product.id);
  await page.goto(`/menu/${product.slug}`);
  await expect(page.getByRole('heading', { name: product.name, exact: true })).toBeVisible();
  await expect(page.locator('body')).toContainText('Reguler');
  await expect(page.locator('body')).toContainText(/Rp\s*25\.000/);
  await expect(page.locator('img[src*="uploads/products/"]').first()).toBeVisible();
  await expect(page.locator('#ulasan-produk')).toContainText('Belum ada ulasan');
});

test('PUB-03 FR-02 produk nonaktif hilang dari beranda, detail, dan POS', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await guest.goto('/');
  await expect(guest.locator(`[data-product-id="${product.id}"]`).first()).toBeVisible();
  await owner.goto('/panel/catalog');
  await filterTable(owner, 'Semua data produk', product.name);
  await submit(owner, idForm(owner, '/panel/catalog/products/status', 'product_id', product.id));
  await flash(owner, /disembunyikan dari katalog/);
  await guest.goto('/');
  await expect(guest.locator(`[data-product-card][data-product-id="${product.id}"]`)).toHaveCount(0);
  expect((await guest.goto(`/menu/${product.slug}`))!.status()).toBe(404);
  await openShift(cashier, db);
  await cashier.goto('/panel/pos');
  await expect(cashier.locator(`[data-add-product][data-variant-id="${product.variant_id}"]`)).toHaveCount(0);
});

test('PUB-04 FR-02 pencarian dan kategori menyaring katalog', async ({ ownerPage: owner, guestPage: page, db }) => {
  const category = await createCategory(owner, db);
  const product = await createProduct(owner, db, { stockMode: 'none', categoryId: category.id });
  await page.goto('/');
  await page.getByLabel('Cari menu', { exact: true }).fill(product.name);
  await expect(page.locator('[data-product-card]:visible')).toHaveCount(1);
  await page.getByLabel('Cari menu', { exact: true }).fill('');
  await page.locator(`[data-category-filter="${category.slug}"]`).click();
  await expect(page.locator('[data-product-card]:visible')).toHaveCount(1);
  await expect(page.locator('[data-product-card]:visible')).toContainText(product.name);
});

test('PUB-06 FR-02 simpan identitas menonaktifkan penanda demo', async ({ ownerPage: owner, guestPage: page, db }) => {
  await page.goto('/');
  await expect(page.getByText(/Tampilan data contoh\./)).toBeVisible();
  await owner.goto('/panel/settings');
  await owner.getByLabel('Nama usaha', { exact: true }).fill('Marwis Sweet E2E');
  await save(owner, '/panel/settings/business', /disimpan|berhasil/i);
  await page.goto('/');
  await expect(page.getByText(/Tampilan data contoh\./)).toHaveCount(0);
  await expect(page.locator('body')).toContainText('Marwis Sweet E2E');
});
