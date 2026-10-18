import { test, expect } from '../fixtures';
import { createBanner, bannerForm } from '../helpers/uploads';
import { formAt, idForm, filterTable } from '../helpers/data';
import { submit, flash } from '../helpers/flash';

test('BAN-01 FR-10 banner aktif tampil dengan alt text', async ({ ownerPage: owner, guestPage: guest, db }) => {
  const banner = await createBanner(owner, db);
  await guest.goto('/');
  await expect(guest.getByRole('img', { name: banner.alt, exact: true })).toHaveCount(1);
  expect((await db.one('SELECT is_active FROM banners WHERE id=?', [banner.id])).is_active).toBe(1);
});

test('BAN-02 AC-20 banner nonaktif, lewat jadwal, dan belum waktunya tidak tampil', async ({ ownerPage: owner, guestPage: guest, db }) => {
  const inactive = await createBanner(owner, db);
  await owner.goto('/panel/settings');
  await filterTable(owner, 'Semua data banner', inactive.title);
  await submit(owner, idForm(owner, '/panel/settings/banners/status', 'banner_id', inactive.id));
  await flash(owner, /dinonaktifkan/);
  const past = await createBanner(owner, db, { starts: '2000-01-01T10:00', ends: '2000-01-02T10:00' });
  const future = await createBanner(owner, db, { starts: '2099-01-01T10:00', ends: '2099-01-02T10:00' });
  await guest.goto('/');
  for (const banner of [inactive, past, future]) await expect(guest.getByRole('img', { name: banner.alt, exact: true })).toHaveCount(0);
});

test('BAN-03 FR-10 tautan javascript ditolak', async ({ ownerPage: owner, db }) => {
  const { title } = await bannerForm(owner, { link: 'javascript:alert(1)' });
  await submit(owner, formAt(owner, '/panel/settings/banners'));
  await flash(owner, /Tautan banner harus berupa URL internal, HTTP, atau HTTPS/);
  expect(await db.count('banners', 'title=?', [title])).toBe(0);
});

test('BAN-05 FR-10 carousel dapat dioperasikan dengan keyboard', async ({ ownerPage: owner, guestPage: guest, db }) => {
  await createBanner(owner, db);
  await createBanner(owner, db);
  await guest.goto('/');
  const next = guest.getByRole('button', { name: 'Promo berikutnya', exact: true });
  await next.focus();
  await expect(next).toBeFocused();
  const before = await guest.locator('[data-carousel-status]').innerText();
  await next.press('Enter');
  await expect(guest.locator('[data-carousel-status]')).not.toHaveText(before);
});

test('BAN-04 FR-10 hero tetap tersedia ketika seluruh banner dinonaktifkan', async ({ ownerPage: owner, guestPage: guest, db }) => {
  const banners = await db.rows('SELECT id,title FROM banners WHERE is_active=1');
  for (const banner of banners) {
    await owner.goto('/panel/settings');
    await filterTable(owner, 'Semua data banner', banner.title);
    await submit(owner, idForm(owner, '/panel/settings/banners/status', 'banner_id', banner.id));
    await flash(owner, /dinonaktifkan/);
  }
  await guest.goto('/');
  await expect(guest.locator('[data-carousel-slide]')).toHaveCount(0);
  await expect(guest.getByRole('heading', { level: 1 })).toBeVisible();
  const business = await db.one("SELECT setting_value name FROM settings WHERE setting_key='business_name'");
  await expect(guest.locator('body')).toContainText(business.name);
});
