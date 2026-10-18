import { type APIResponse, type Locator, type Page } from '@playwright/test';
import { test, expect } from '../fixtures';
import { Database } from '../helpers/db';
import { cart, createProduct, formAt, openShift, pay, stockedProduct, unique } from '../helpers/data';
import { requestRefund } from '../helpers/finance';
import { postWithCsrf } from '../helpers/http';
import { fillReview, reviewSale } from '../helpers/reviews';
import { jpeg, productPhoto } from '../helpers/uploads';

const protectedTables = [
  'sales', 'sale_items', 'payments', 'refunds', 'refund_items', 'expenses',
  'journal_entries', 'journal_lines', 'stock_movements', 'product_reviews',
  'review_images', 'product_images', 'audit_events',
];

async function counts(db: Database) {
  return Object.fromEntries(await Promise.all(protectedTables.map(async (table) => [table, await db.count(table)])));
}

async function fields(form: Locator): Promise<Record<string, string>> {
  return form.evaluate((element: HTMLFormElement) => Object.fromEntries(
    [...new FormData(element).entries()].filter((entry): entry is [string, string] => typeof entry[1] === 'string'),
  ));
}

async function rejected(page: Page, response: APIResponse, pattern: RegExp, guest = false) {
  expect(response.status(), await response.text()).toBe(303);
  const destination = new URL(response.headers().location, page.url());
  expect(destination.origin).toBe(new URL(page.url()).origin);
  await page.goto(destination.href);
  await expect(page.locator(guest ? '[role="alert"]' : '#flash-message')).toContainText(pattern);
  await expect(page.locator('body')).not.toContainText(/SecurityException|CSRF token|The action you requested is not allowed/i);
}

test.afterAll(async () => {
  const db = new Database();
  try { await db.assertJournalsBalanced(); } finally { await db.close(); }
});

test('SRV-01 GAP-03 server menolak refund melebihi sisa unit setelah refund pertama', async ({ ownerPage: owner, cashierPage: cashier, db }) => {
  const { product, item: inventory } = await stockedProduct(owner, db, { direct: true, quantity: 5 });
  await openShift(cashier, db);
  await cart(cashier, product.variant_id, { quantity: 2 });
  const sale = await pay(cashier, db);
  await requestRefund(cashier, db, sale.id, 1, true);
  const item = await db.one('SELECT * FROM sale_items WHERE sale_id=?', [sale.id]);
  const form = formAt(cashier, `/panel/transactions/${sale.id}/refunds`);
  const payload = await fields(form);
  payload[`quantities[${item.id}]`] = '2';
  payload.reason = 'Pengujian batas sisa refund pada server';
  const before = await counts(db);
  const beforeSale = await db.one('SELECT * FROM sales WHERE id=?', [sale.id]);
  const beforeStock = await db.one('SELECT * FROM inventory_items WHERE id=?', [inventory.id]);
  const beforeRefunds = await db.rows('SELECT * FROM refunds WHERE sale_id=? ORDER BY id', [sale.id]);
  const beforeRefundItems = await db.rows('SELECT * FROM refund_items WHERE sale_item_id=? ORDER BY id', [item.id]);

  const response = await postWithCsrf(cashier, `/panel/transactions/${sale.id}/refunds`, payload);
  await rejected(cashier, response, /Jumlah refund melebihi sisa unit/);

  expect(await counts(db)).toEqual(before);
  expect(await db.one('SELECT * FROM sales WHERE id=?', [sale.id])).toEqual(beforeSale);
  expect(await db.one('SELECT * FROM sale_items WHERE id=?', [item.id])).toEqual(item);
  expect(await db.one('SELECT * FROM inventory_items WHERE id=?', [inventory.id])).toEqual(beforeStock);
  expect(await db.rows('SELECT * FROM refunds WHERE sale_id=? ORDER BY id', [sale.id])).toEqual(beforeRefunds);
  expect(await db.rows('SELECT * FROM refund_items WHERE sale_item_id=? ORDER BY id', [item.id])).toEqual(beforeRefundItems);
});

test('SRV-02 GAP-03 server menolak pengeluaran nominal nol meskipun batas HTML dilewati', async ({ cashierPage: cashier, db }) => {
  await cashier.goto('/panel/data/expense/create');
  await cashier.getByLabel('Kategori', { exact: true }).selectOption({ index: 1 });
  const description = unique('Nominal nol melalui request');
  await cashier.getByLabel('Deskripsi', { exact: true }).fill(description);
  await cashier.getByLabel('Nominal', { exact: true }).fill('10000');
  await cashier.getByLabel('Sumber pembayaran', { exact: true }).selectOption('main_cash');
  const payload = await fields(formAt(cashier, '/panel/expenses/create'));
  payload.amount = '0';
  const before = await counts(db);

  const response = await postWithCsrf(cashier, '/panel/expenses/create', payload);
  await rejected(cashier, response, /deskripsi dan nominal pengeluaran dengan benar/i);

  expect(await counts(db)).toEqual(before);
  expect(await db.count('expenses', 'description=?', [description])).toBe(0);
});

const invalidReviews = [
  { label: 'komentar 9 karakter', comment: 'a'.repeat(9), rating: '5', error: /Komentar harus berisi 10 sampai 1\.000 karakter/ },
  { label: 'komentar 1001 karakter', comment: 'a'.repeat(1001), rating: '5', error: /Komentar harus berisi 10 sampai 1\.000 karakter/ },
  { label: 'rating 0', comment: 'Komentar valid untuk pengujian rating', rating: '0', error: /Pilih produk dan rating 1 sampai 5/ },
  { label: 'rating 6', comment: 'Komentar valid untuk pengujian rating', rating: '6', error: /Pilih produk dan rating 1 sampai 5/ },
];

for (const [index, invalid] of invalidReviews.entries()) {
  test(`SRV-0${index + 3} GAP-03 server menolak ${invalid.label}`, async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
    const { item, sale, link } = await reviewSale(owner, cashier, db);
    await guest.goto(link);
    await fillReview(guest, item.id, 'Komentar valid sebelum batas HTML dilewati');
    const payload = await fields(guest.locator('form[method="post"][enctype="multipart/form-data"]'));
    payload.comment = invalid.comment;
    payload.rating = invalid.rating;
    const before = await counts(db);
    const tokens = await db.rows('SELECT * FROM review_tokens WHERE sale_id=? ORDER BY id', [sale.id]);

    const response = await postWithCsrf(guest, link, payload);
    await rejected(guest, response, invalid.error, true);

    expect(await counts(db)).toEqual(before);
    expect(await db.count('product_reviews', 'sale_item_id=?', [item.id])).toBe(0);
    expect(await db.one('SELECT * FROM sales WHERE id=?', [sale.id])).toEqual(sale);
    expect(await db.rows('SELECT * FROM review_tokens WHERE sale_id=? ORDER BY id', [sale.id])).toEqual(tokens);
  });
}

test('SRV-07 GAP-03 server menolak unggahan foto produk keenam', async ({ ownerPage: owner, db }) => {
  test.setTimeout(120_000);
  const product = await createProduct(owner, db, { stockMode: 'none' });
  for (let index = 0; index < 5; index++) await productPhoto(owner, product.id);
  await expect(owner.getByRole('heading', { name: 'Foto produk (5/5)', exact: true })).toBeVisible();
  const before = await counts(db);
  const images = await db.rows('SELECT * FROM product_images WHERE product_id=? ORDER BY id', [product.id]);
  const beforeProduct = await db.one('SELECT * FROM products WHERE id=?', [product.id]);
  const csrf = await owner.locator('input[name="csrf_test_name"]').first().inputValue();

  const response = await owner.request.post('/panel/catalog/products/images', {
    multipart: { product_id: String(product.id), alt_text: 'Foto keenam harus ditolak server', csrf_test_name: csrf, image: jpeg() },
    maxRedirects: 0,
  });
  await rejected(owner, response, /maksimal 5 foto/);

  expect(await counts(db)).toEqual(before);
  expect(await db.rows('SELECT * FROM product_images WHERE product_id=? ORDER BY id', [product.id])).toEqual(images);
  expect(await db.one('SELECT * FROM products WHERE id=?', [product.id])).toEqual(beforeProduct);
});

test('SRV-08 GAP-03 server menolak empat foto pada satu ulasan', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
  const { item, sale, link } = await reviewSale(owner, cashier, db);
  await guest.goto(link);
  await fillReview(guest, item.id, 'Komentar valid untuk pengujian batas empat foto');
  const payload = await fields(guest.locator('form[method="post"][enctype="multipart/form-data"]'));
  const multipart = new FormData();
  for (const [name, value] of Object.entries(payload)) multipart.append(name, value);
  const photo = jpeg();
  for (let index = 0; index < 4; index++) {
    multipart.append('photos[]', new Blob([new Uint8Array(photo.buffer)], { type: photo.mimeType }), `e2e-ulasan-${index + 1}.jpg`);
  }
  const before = await counts(db);
  const tokens = await db.rows('SELECT * FROM review_tokens WHERE sale_id=? ORDER BY id', [sale.id]);

  const response = await guest.request.post(link, { multipart, maxRedirects: 0 });
  await rejected(guest, response, /Maksimal tiga foto untuk satu ulasan/, true);

  expect(await counts(db)).toEqual(before);
  expect(await db.count('product_reviews', 'sale_item_id=?', [item.id])).toBe(0);
  expect(await db.one('SELECT * FROM sales WHERE id=?', [sale.id])).toEqual(sale);
  expect(await db.rows('SELECT * FROM review_tokens WHERE sale_id=? ORDER BY id', [sale.id])).toEqual(tokens);
});
