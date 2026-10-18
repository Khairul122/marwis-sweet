import { test as base, expect, type Page } from '@playwright/test';
import { Database } from './helpers/db';
import { authenticated } from './helpers/auth';

export const test = base.extend<{ ownerPage: Page; cashierPage: Page; guestPage: Page; db: Database }>({
  ownerPage: async ({ browser }, use) => {
    const context = await browser.newContext({ baseURL: 'http://localhost:8081/', viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    await authenticated(page, 'owner');
    await use(page);
    await context.close();
  },
  cashierPage: async ({ browser }, use) => {
    const context = await browser.newContext({ baseURL: 'http://localhost:8081/', viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    await authenticated(page, 'cashier');
    await use(page);
    await context.close();
  },
  guestPage: async ({ page }, use) => { await use(page); },
  db: async ({}, use) => {
    const db = new Database();
    await use(db);
    await db.close();
  },
});
export { expect };
