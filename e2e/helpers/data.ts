import { expect, type Page } from '@playwright/test';
import { randomBytes } from 'node:crypto';
import { Database } from './db';
import { dismissFlash, flash, submit } from './flash';

export const unique = (label = 'Data') => `E2E-${Date.now()}-${randomBytes(3).toString('hex')}-${label}`;
export const formAt = (page: Page, action: string) => page.locator(`form[action$="${action}"]`);
export const idForm = (page: Page, action: string, field: string, id: number) => formAt(page, action).filter({ has: page.locator(`input[name="${field}"][value="${id}"]`) });
export async function filterTable(page: Page, title: string, search: string) {
  await page.locator('section').filter({ has: page.getByRole('heading', { name: title, exact: true }) }).locator('[data-dt-search]').fill(search);
}

export async function save(page: Page, action: string, message: RegExp = /berhasil|disimpan|tersimpan|ditambahkan|dibuat/i) {
  await submit(page, formAt(page, action));
  await flash(page, message);
}

export async function createCashier(page: Page, db: Database) {
  const username = unique('kasir');
  await page.goto('/panel/data/cashier/create');
  await page.getByLabel('Nama lengkap', { exact: true }).fill(username);
  await page.getByLabel('Username', { exact: true }).fill(username);
  await page.getByLabel(/Password awal/).fill('E2ECashier!2026');
  await save(page, '/panel/users/create');
  const account = await db.one('SELECT id,username,name FROM users WHERE username=?', [username]);
  return { id: Number(account.id), username: String(account.username), name: String(account.name), password: 'E2ECashier!2026' };
}

export async function createCategory(page: Page, db: Database) {
  const name = unique('Kategori');
  await page.goto('/panel/data/category/create');
  await page.getByLabel('Nama kategori', { exact: true }).fill(name);
  await save(page, '/panel/catalog/categories');
  return db.one('SELECT * FROM categories WHERE name=?', [name]);
}

export async function createItem(page: Page, db: Database, unit = 'gram') {
  const name = unique('Bahan');
  await page.goto('/panel/data/item/create');
  await page.getByLabel('Nama bahan', { exact: true }).fill(name);
  await page.getByLabel('SKU unik', { exact: true }).fill(name.toUpperCase());
  await page.getByLabel('Satuan dasar', { exact: true }).selectOption(unit);
  await save(page, '/panel/master/items');
  return db.one('SELECT * FROM inventory_items WHERE name=?', [name]);
}

export async function conversion(page: Page, itemId: number, unit = 'kg', factor = 1000) {
  await page.goto(`/panel/data/item/${itemId}/edit`);
  await page.getByLabel('Satuan pembelian', { exact: true }).fill(unit);
  await page.getByLabel(/^Isi dalam/).fill(String(factor));
  await save(page, '/panel/master/conversions');
}

export async function createProduct(page: Page, db: Database, options: {
  itemId?: number; stockMode?: 'recipe' | 'direct' | 'none'; price?: number; categoryId?: number; name?: string;
} = {}) {
  const name = options.name ?? unique('Produk');
  const category = options.categoryId ?? (await db.rows('SELECT id FROM categories WHERE is_active=1 ORDER BY id LIMIT 1'))[0].id;
  await page.goto('/panel/data/product/create');
  await page.getByLabel('Nama produk', { exact: true }).fill(name);
  await page.getByLabel('Kategori', { exact: true }).selectOption(String(category));
  await page.getByLabel('Deskripsi singkat').fill(`Produk pengujian ${name}`);
  await page.getByLabel('Jenis produk', { exact: true }).selectOption(options.stockMode === 'direct' ? 'stocked' : 'prepared');
  await page.getByLabel('Nama varian', { exact: true }).fill('Reguler');
  await page.getByLabel('SKU unik', { exact: true }).fill(unique('SKU').toUpperCase());
  await page.getByLabel('Harga jual', { exact: true }).fill(String(options.price ?? 25000));
  await page.getByLabel('Pengurangan stok', { exact: true }).selectOption(options.stockMode ?? 'recipe');
  if (options.stockMode === 'direct') await page.getByLabel(/^Item stok langsung/).selectOption(String(options.itemId));
  await save(page, '/panel/catalog/products');
  const product = await db.one(`SELECT p.*, v.id variant_id, v.price FROM products p JOIN product_variants v ON v.product_id=p.id WHERE p.name=?`, [name]);
  if ((options.stockMode ?? 'recipe') === 'recipe' && options.itemId) await recipe(page, product.variant_id, options.itemId, 100);
  return product;
}

export async function recipe(page: Page, variantId: number, itemId: number, quantity: number) {
  await page.goto(`/panel/master/recipes/${variantId}`);
  await page.getByLabel('Bahan', { exact: true }).selectOption(String(itemId));
  await page.getByLabel('Jumlah', { exact: true }).fill(String(quantity));
  await save(page, '/panel/master/recipes');
}

export async function receiptDraft(page: Page, db: Database, itemId: number, quantity = 1000, cost = 12, unit = 'gram') {
  const reference = unique('PB').toUpperCase();
  await page.goto('/panel/inventory/receipts/new');
  await page.getByLabel('Nomor referensi', { exact: true }).fill(reference);
  await submit(page, formAt(page, '/panel/inventory/receipts'));
  await flash(page, /berhasil|disimpan|dibuat/i);
  const receipt = await db.one('SELECT * FROM goods_receipts WHERE reference_number=?', [reference]);
  await page.getByLabel('Bahan', { exact: true }).selectOption(String(itemId));
  await page.getByLabel('Jumlah beli', { exact: true }).fill(String(quantity));
  await page.getByLabel('Satuan beli', { exact: true }).fill(unit);
  await page.getByLabel('Biaya per satuan (Rp)', { exact: true }).fill(String(cost));
  await save(page, '/panel/inventory/receipts/items');
  return receipt;
}

export async function postReceipt(creator: Page, owner: Page, id: number) {
  const url = `/panel/inventory/receipts/${id}`;
  await creator.goto(url);
  await save(creator, `${url}/submit`, /diajukan|berhasil/i);
  await owner.goto(url);
  await save(owner, `${url}/approve`, /disetujui|berhasil/i);
  await creator.goto(url);
  await save(creator, '/panel/inventory/receipts/post', /diposting|berhasil/i);
  await creator.goto(url);
  await expect(creator.getByRole('heading', { name: 'Sudah diposting', exact: true })).toBeVisible();
}

export async function stockedProduct(owner: Page, db: Database, options: { direct?: boolean; quantity?: number; price?: number } = {}) {
  const item = await createItem(owner, db, options.direct ? 'pcs' : 'gram');
  const receipt = await receiptDraft(owner, db, item.id, options.quantity ?? 2000, options.direct ? 4000 : 12, options.direct ? 'pcs' : 'gram');
  await postReceipt(owner, owner, receipt.id);
  const product = await createProduct(owner, db, { itemId: item.id, stockMode: options.direct ? 'direct' : 'recipe', price: options.price });
  return { item, product };
}

export async function openShift(page: Page, db: Database, opening = 200000) {
  await page.goto('/panel/shifts');
  if (await formAt(page, '/panel/shifts/open').count()) {
    await page.getByLabel('Modal awal', { exact: true }).fill(String(opening));
    await save(page, '/panel/shifts/open', /dibuka|berhasil/i);
  }
  return (await db.rows(`SELECT s.* FROM shifts s JOIN users u ON u.id=s.cashier_id WHERE u.username='kasir' AND s.status='open'`))[0];
}

export async function cart(page: Page, variantId: number, options: { quantity?: number; discount?: number; cash?: number; split?: number; reference?: string; ownerApproval?: boolean } = {}) {
  await page.goto('/panel/pos');
  await dismissFlash(page);
  await page.locator(`[data-add-product][data-variant-id="${variantId}"]`).click();
  const open = page.locator('[data-pos-cart-open]');
  if (await open.isVisible()) await open.click();
  for (let i = 1; i < (options.quantity ?? 1); i++) await page.locator('[data-cart-increase]').click();
  if (options.discount) await page.getByLabel('Nilai diskon transaksi', { exact: true }).fill(String(options.discount));
  if (options.split) {
    await page.getByLabel('Gunakan pembayaran gabungan', { exact: true }).check();
    await page.locator('[data-payment-amount="1"]').fill(String(options.split));
    await page.getByLabel('Metode kedua', { exact: true }).selectOption('qris');
    await page.locator('[data-payment-reference="2"]').fill(options.reference ?? unique('QRIS'));
  }
  await page.locator('[data-cash-received="1"]').fill(String(options.cash ?? 100000));
  if (options.ownerApproval && await page.locator('[data-discount-approval]').isVisible()) {
    await page.getByLabel('Username atau email owner', { exact: true }).fill('owner');
    await page.getByLabel('Kata sandi owner', { exact: true }).fill('DemoOwner!2026');
  }
}

export async function pay(page: Page, db: Database) {
  await submit(page, page.locator('[data-pos-checkout]'), 'Bayar sekarang');
  await expect(page).toHaveURL(/\/panel\/transactions\/\d+$/);
  await flash(page, /Pembayaran berhasil/);
  const id = Number(page.url().split('/').pop());
  await expect(page.locator('#receipt')).toContainText('Lunas');
  return db.one('SELECT * FROM sales WHERE id=?', [id]);
}

export async function opname(page: Page, db: Database, itemId: number) {
  const notes = unique('Opname');
  await page.goto('/panel/opnames');
  await page.getByLabel('Sebagian', { exact: true }).check();
  await page.locator(`input[name="item_ids[]"][value="${itemId}"]`).check();
  await page.getByLabel(/^Catatan sesi/).fill(notes);
  await save(page, '/panel/opnames/create', /dimulai|dibuat|berhasil/i);
  return db.one('SELECT * FROM stock_opnames WHERE notes=?', [notes]);
}
