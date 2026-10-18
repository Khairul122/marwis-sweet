import { test, expect } from '../fixtures';
import { createCashier, createProduct, formAt, save, unique } from '../helpers/data';
import { login } from '../helpers/auth';
import { invalidImages, bannerForm, productPhoto } from '../helpers/uploads';
import { submit, flash } from '../helpers/flash';

test('SEC-01 PRD-10 POST tanpa CSRF ditolak tanpa perubahan data', async ({ ownerPage: page, db }) => {
  await page.goto('/panel/users');
  const before = await db.count('users');
  const response = await page.request.post('/panel/users/create', {
    form: { name: unique('CSRF'), username: unique('csrf'), password: 'E2ECashier!2026' }, maxRedirects: 0,
  });
  expect(response.status()).toBe(403);
  expect(await db.count('users')).toBe(before);
  await expect(page.getByRole('heading', { name: 'Akun kasir', exact: true })).toBeVisible();
});

test('SEC-02 PRD-10 bukti pengeluaran kasir lain tidak dapat dibuka', async ({ ownerPage: owner, cashierPage: cashier, guestPage: other, db }) => {
  const account = await createCashier(owner, db);
  await login(other, account.username, account.password);
  await other.goto('/panel/data/expense/create');
  await other.getByLabel('Kategori', { exact: true }).selectOption({ index: 1 });
  await other.getByLabel('Deskripsi', { exact: true }).fill(unique('Bukti privat'));
  await other.getByLabel('Nominal', { exact: true }).fill('10000');
  await other.getByLabel('Sumber pembayaran', { exact: true }).selectOption('main_cash');
  await other.getByLabel(/^Bukti \(/).setInputFiles({ name: 'bukti.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%%EOF') });
  await save(other, '/panel/expenses/create');
  const expense = (await db.rows('SELECT * FROM expenses WHERE created_by=? ORDER BY id DESC LIMIT 1', [account.id]))[0];
  expect(expense.evidence_path).toBeTruthy();
  const response = await cashier.goto(`/panel/expenses/${expense.id}/evidence`);
  expect(response!.status()).toBe(404);
  await expect(cashier.locator('body')).toContainText(/404|tidak ditemukan/i);
  await cashier.goto('/panel/expenses');
  await expect(cashier.getByText(expense.description, { exact: true })).toHaveCount(0);
  await owner.goto('/panel/expenses');
  await expect(owner.getByText(expense.description, { exact: true })).toBeVisible();
  expect(await db.count('expenses', 'id=?', [expense.id])).toBe(1);
});

for (const entity of ['product', 'banner'] as const) {
  for (const index of [0, 1, 2]) {
    test(`SEC-03 PRD-10 upload ${entity} tidak sah ditolak kasus ${index + 1}`, async ({ ownerPage: owner, db }) => {
      const file = invalidImages()[index];
      if (index === 0) { file.name = 'e2e-palsu.png'; file.mimeType = 'image/png'; }
      if (entity === 'product') {
        const product = await createProduct(owner, db, { stockMode: 'none' });
        await owner.goto(`/panel/data/product/${product.id}/edit`);
        const form = formAt(owner, '/panel/catalog/products/images');
        await form.getByLabel('Foto JPEG, PNG, atau WebP', { exact: true }).setInputFiles(file);
        await form.getByLabel('Deskripsi foto', { exact: true }).fill('Pengujian berkas produk tidak sah');
        await submit(owner, form);
        await flash(owner, /gambar|JPEG|PNG|WebP|berkas|valid/i);
        expect(await db.count('product_images', 'product_id=?', [product.id])).toBe(0);
      } else {
        const { title } = await bannerForm(owner);
        await owner.getByLabel(/^Gambar \(maksimal/).setInputFiles(file);
        await submit(owner, formAt(owner, '/panel/settings/banners'));
        await flash(owner, /gambar|JPEG|PNG|WebP|berkas|valid/i);
        expect(await db.count('banners', 'title=?', [title])).toBe(0);
      }
    });
  }
}

test('SEC-04 PRD-10 nama berkas gambar disimpan acak', async ({ ownerPage: owner, db }) => {
  const product = await createProduct(owner, db, { stockMode: 'none' });
  await productPhoto(owner, product.id);
  const image = await db.one('SELECT image_path FROM product_images WHERE product_id=?', [product.id]);
  expect(image.image_path).toMatch(/^uploads\/products\/[a-f0-9]{32}\.jpg$/);
  await expect(owner.getByRole('heading', { name: 'Foto produk (1/5)', exact: true })).toBeVisible();
});

test('SEC-05 PRD-10 nama produk berisi script di-escape pada halaman publik', async ({ ownerPage: owner, guestPage: guest, db }) => {
  const name = unique('<script>alert("e2e")</script>');
  const product = await createProduct(owner, db, { stockMode: 'none', name });
  let dialogs = 0;
  guest.on('dialog', async (dialog) => { dialogs++; await dialog.dismiss(); });
  const response = await guest.goto(`/menu/${product.slug}`);
  await expect(guest.getByRole('heading', { name, exact: true })).toBeVisible();
  expect(await response!.text()).toContain('&lt;script&gt;');
  expect(dialogs).toBe(0);
});

test('SEC-06 PRD-10 header keamanan tersedia', async ({ guestPage: guest }) => {
  const response = await guest.goto('/login');
  const headers = response!.headers();
  expect(headers['x-frame-options']).toBeTruthy();
  expect(headers['x-content-type-options']).toBe('nosniff');
  expect(headers['referrer-policy']).toBeTruthy();
  await expect(guest.getByRole('heading', { name: 'Masuk ke akun' })).toBeVisible();
});

test.skip('SEC-07 AC-21 HTTPS perlu domain dan sertifikat staging', async () => {});
