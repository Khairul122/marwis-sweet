import { expect, type Page } from '@playwright/test';
import { PDFParse } from 'pdf-parse';

export async function pdf(page: Page, path: string) {
  const response = await page.request.get(path);
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('application/pdf');
  const buffer = await response.body();
  expect(buffer.subarray(0, 4).toString()).toBe('%PDF');
  const parser = new PDFParse({ data: new Uint8Array(buffer) });
  try {
    const text = await parser.getText();
    return { text: text.text, pages: text.total, pageTexts: text.pages.map((page) => page.text), buffer };
  } finally { await parser.destroy(); }
}
