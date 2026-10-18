import { test, expect } from '../fixtures';
import { reviewSale, fillReview } from '../helpers/reviews';
import { jpeg, invalidImages } from '../helpers/uploads';
import { submit, flash } from '../helpers/flash';
import { formAt, save, unique } from '../helpers/data';
import { postWithCsrf } from '../helpers/http';

test('REV-01/02/08 AC-18 ulasan tamu pending, disetujui, lalu disembunyikan', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
  const { product, item, link } = await reviewSale(owner, cashier, db);
  const comment = unique('Martabak enak dan porsinya sesuai');
  await guest.goto(link);
  await fillReview(guest, item.id, comment);
  await guest.getByLabel(/^Foto /).setInputFiles(jpeg());
  await submit(guest, guest.locator('form'));
  await expect(guest.getByRole('status')).toContainText('Ulasan tersimpan');
  const review = await db.one('SELECT * FROM product_reviews WHERE sale_item_id=?', [item.id]);
  expect(review.status).toBe('pending');
  expect(await db.count('review_images', 'product_review_id=?', [review.id])).toBe(1);
  await guest.goto(`/menu/${product.slug}`);
  await expect(guest.getByRole('blockquote').filter({ hasText: comment })).toHaveCount(0);
  await owner.goto('/panel/reviews');
  const approve = formAt(owner, `/panel/reviews/${review.id}/moderate`).filter({ has: owner.locator('input[name="action"][value="approve"]') });
  await submit(owner, approve);
  await flash(owner, /Status ulasan diperbarui/);
  await guest.goto(`/menu/${product.slug}`);
  await expect(guest.getByRole('blockquote').filter({ hasText: comment })).toBeVisible();
  await expect(guest.locator('#ulasan-produk')).toContainText(/1 ulasan|dari 1/);
  await expect(guest.getByText('5,0', { exact: true }).first()).toBeVisible();
  expect((await db.one('SELECT rating,status FROM product_reviews WHERE id=?', [review.id]))).toEqual({ rating: 5, status: 'approved' });
  await owner.goto('/panel/reviews');
  const hide = formAt(owner, `/panel/reviews/${review.id}/moderate`);
  await hide.getByLabel('Alasan menyembunyikan').fill('Moderasi ulang untuk pengujian E2E');
  await submit(owner, hide);
  await guest.goto(`/menu/${product.slug}`);
  await expect(guest.getByRole('blockquote').filter({ hasText: comment })).toHaveCount(0);
  expect((await db.one('SELECT status FROM product_reviews WHERE id=?', [review.id])).status).toBe('hidden');
});

test('REV-03 AC-19 pengiriman ulang untuk item sama ditolak', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
  const { item, link } = await reviewSale(owner, cashier, db);
  await guest.goto(link);
  await fillReview(guest, item.id, 'Komentar pertama untuk pengujian duplikasi');
  await submit(guest, guest.locator('form'));
  await expect(guest.getByRole('heading', { name: 'Semua produk sudah diulas.' })).toBeVisible();
  await guest.goto('/login');
  const response = await postWithCsrf(guest, link, { sale_item_id: String(item.id), rating: '5', comment: 'Komentar duplikat tidak boleh tersimpan' });
  expect(response.status()).toBe(303);
  await guest.goto(link);
  await expect(guest.getByRole('alert')).toContainText(/Pilih produk dan rating/);
  expect(await db.count('product_reviews', 'sale_item_id=?', [item.id])).toBe(1);
});

test('REV-04 AC-19 token kedaluwarsa tidak membuka data transaksi', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
  const { sale, link } = await reviewSale(owner, cashier, db);
  const token = await db.one('SELECT id FROM review_tokens WHERE sale_id=?', [sale.id]);
  await db.fixtureDate('review_tokens', token.id, 'expires_at', '2000-01-01 00:00:00');
  await guest.goto(link);
  await expect(guest.getByRole('heading', { name: 'Tautan tidak tersedia.' })).toBeVisible();
  await expect(guest.getByText(sale.sale_number, { exact: false })).toHaveCount(0);
});

for (const index of [0, 1, 2]) {
  test(`REV-05 AC-19 foto tidak sah ditolak kasus ${index + 1}`, async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
    const { item, link } = await reviewSale(owner, cashier, db);
    await guest.goto(link);
    await fillReview(guest, item.id, 'Pengujian penolakan lampiran foto tidak sah');
    await guest.getByLabel(/^Foto /).setInputFiles(invalidImages()[index]);
    await submit(guest, guest.locator('form'));
    await expect(guest.getByRole('alert')).toContainText(/foto|JPEG|PNG|WebP|maksimal|valid/i);
    expect(await db.count('product_reviews', 'sale_item_id=?', [item.id])).toBe(0);
  });
}

test('REV-06 FR-11 komentar pendek ditolak, rating hanya 1 sampai 5', async ({ ownerPage: owner, cashierPage: cashier, guestPage: guest, db }) => {
  const { item, link } = await reviewSale(owner, cashier, db);
  await guest.goto(link);
  await fillReview(guest, item.id, 'Pendek');
  await guest.getByRole('button', { name: 'Kirim ulasan' }).click();
  expect(await guest.getByLabel('Komentar', { exact: true }).evaluate((input: HTMLTextAreaElement) => input.validity.tooShort)).toBe(true);
  await expect(guest.getByRole('radio')).toHaveCount(5);
  await expect(guest.getByLabel('Komentar', { exact: true })).toHaveAttribute('maxlength', '1000');
  expect(await db.count('product_reviews', 'sale_item_id=?', [item.id])).toBe(0);
});

test('REV-07 FR-11 token acak tidak membocorkan transaksi', async ({ guestPage: guest }) => {
  await guest.goto(`/ulasan/${'0'.repeat(64)}`);
  await expect(guest.getByRole('heading', { name: 'Tautan tidak tersedia.' })).toBeVisible();
  await expect(guest.getByLabel('Produk', { exact: true })).toHaveCount(0);
});
