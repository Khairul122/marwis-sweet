import { expect, type Page, type TestInfo } from '@playwright/test';

export function observe(page: Page, info: TestInfo, name: string) {
  const errors: string[] = [];
  const consoleMessages: { level: string; text: string }[] = [];
  const responses: { status: number; path: string }[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type())) consoleMessages.push({ level: message.type(), text: message.text().slice(0, 500) });
  });
  page.on('response', (response) => {
    if (response.status() >= 400) responses.push({ status: response.status(), path: new URL(response.url()).pathname.replace(/\/ulasan\/[a-f0-9]{64}/, '/ulasan/[token]') });
  });
  return async () => {
    await info.attach(`runtime-${name}.json`, { body: JSON.stringify({ errors, consoleMessages, responses }, null, 2), contentType: 'application/json' });
    if (info.status === 'failed' && !page.isClosed()) {
      const screenshot = info.outputPath(`${name}-failure.png`);
      await page.screenshot({ path: screenshot });
      await info.attach(`${name}-failure`, { path: screenshot, contentType: 'image/png' });
    }
    expect(errors, `JavaScript runtime ${name}`).toEqual([]);
    expect(responses.filter((response) => response.status >= 500), `HTTP 5xx ${name}`).toEqual([]);
  };
}
