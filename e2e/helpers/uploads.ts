import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { type Page } from '@playwright/test';
import { root } from '../scripts/env.mjs';
import { Database } from './db';
import { formAt, save, unique } from './data';

export const jpeg = () => ({ name: 'e2e-foto.jpg', mimeType: 'image/jpeg', buffer: readFileSync(resolve(root, 'public/assets/img/martabak-manis-hero.jpg')) });
export const invalidImages = () => [
  { name: 'e2e-palsu.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('<?php echo "uji"; ?>') },
  { name: 'e2e-vector.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>') },
  { name: 'e2e-besar.jpg', mimeType: 'image/jpeg', buffer: Buffer.concat([jpeg().buffer, Buffer.alloc(6 * 1024 * 1024)]) },
];

export async function productPhoto(owner: Page, productId: number) {
  await owner.goto(`/panel/data/product/${productId}/edit`);
  const form = formAt(owner, '/panel/catalog/products/images');
  await form.getByLabel('Foto JPEG, PNG, atau WebP', { exact: true }).setInputFiles(jpeg());
  await form.getByLabel('Deskripsi foto', { exact: true }).fill(unique('Foto martabak'));
  await save(owner, '/panel/catalog/products/images', /berhasil|ditambahkan|diunggah|disimpan/i);
}

export async function bannerForm(owner: Page, options: { link?: string; starts?: string; ends?: string } = {}) {
  const title = unique('Banner');
  const alt = unique('Promo martabak');
  await owner.goto('/panel/data/banner/create');
  await owner.getByLabel('Judul internal', { exact: true }).fill(title);
  await owner.getByLabel('Teks alternatif', { exact: true }).fill(alt);
  await owner.getByLabel(/^Gambar \(maksimal/).setInputFiles(jpeg());
  if (options.link) await owner.getByLabel(/^Tautan/).fill(options.link);
  if (options.starts) await owner.getByLabel(/^Mulai tampil/).fill(options.starts);
  if (options.ends) await owner.getByLabel(/^Selesai tampil/).fill(options.ends);
  return { title, alt };
}

export async function createBanner(owner: Page, db: Database, options: { link?: string; starts?: string; ends?: string } = {}) {
  const data = await bannerForm(owner, options);
  await save(owner, '/panel/settings/banners', /berhasil|ditambahkan|disimpan/i);
  const row = await db.one('SELECT * FROM banners WHERE title=?', [data.title]);
  return { ...data, id: Number(row.id) };
}
