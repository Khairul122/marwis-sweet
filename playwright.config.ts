import { defineConfig } from '@playwright/test';
import { baseURL } from './e2e/scripts/env.mjs';

export default defineConfig({
  testDir: './e2e/specs',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 8_000 },
  globalSetup: './e2e/global-setup.ts',
  globalTeardown: './e2e/global-teardown.ts',
  reporter: [['list'], ['html', { open: 'never', outputFolder: process.env.E2E_EVALUATION === '1' ? 'writable/e2e/evaluation-report' : 'playwright-report' }],
    ['json', { outputFile: process.env.E2E_EVALUATION === '1' ? 'writable/e2e/evaluation-results.json' : 'test-results/results.json' }]],
  use: {
    baseURL, browserName: 'chromium', viewport: { width: 1440, height: 1000 },
    trace: 'retain-on-failure', screenshot: 'only-on-failure',
    actionTimeout: 10_000, navigationTimeout: 20_000,
  },
  projects: [
    { name: 'chromium', grepInvert: /@mobile/ },
    { name: 'mobile', grep: /@mobile/, use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
  webServer: {
    command: 'node e2e/scripts/serve.mjs', url: `${baseURL}login`,
    reuseExistingServer: false, timeout: 30_000,
  },
});
