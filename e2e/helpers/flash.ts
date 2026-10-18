import { expect, type Page, type Locator, type Dialog } from '@playwright/test';

export async function dismissFlash(page: Page) {
  await page.waitForLoadState('load');
  const dialog = page.locator('[data-flash-dialog][open]');
  if (await dialog.count()) await dialog.getByRole('button', { name: 'Mengerti' }).click();
}

export async function flash(page: Page, pattern: string | RegExp) {
  await expect(page.locator('#flash-message, #pos-content > [role="alert"], #pos-content > [role="status"]')).toContainText(pattern);
  await dismissFlash(page);
}

export async function submit(page: Page, form: Locator, button?: string | RegExp) {
  await dismissFlash(page);
  const native = (dialog: Dialog) => dialog.accept();
  page.on('dialog', native);
  try {
    const navigation = page.waitForNavigation({ waitUntil: 'load' });
    await (button ? form.getByRole('button', { name: button, exact: typeof button === 'string' }) : form.locator('button[type=submit]').first()).click();
    const confirm = page.locator('[data-confirm-dialog][open]');
    if (await confirm.count()) await confirm.getByRole('button', { name: 'Lanjutkan' }).click();
    await navigation;
  } finally { page.off('dialog', native); }
}
