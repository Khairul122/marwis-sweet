import { chromium, expect } from '@playwright/test';
import mysql from 'mysql2/promise';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { root, schema, connection, baseURL, environment, prepareDirectories, clearThrottle } from './scripts/env.mjs';
import { authenticated } from './helpers/auth';

export default async function setup() {
  prepareDirectories();
  const probe = spawnSync(process.env.E2E_PHP ?? 'php', ['e2e/scripts/preflight.php'], {
    cwd: root, env: environment(), encoding: 'utf8', windowsHide: true,
  });
  const configuration = JSON.parse(probe.stdout);
  if (configuration.schema !== schema || configuration.baseURL !== baseURL) {
    throw new Error('Preflight gagal: reset database dibatalkan.');
  }
  const admin = await mysql.createConnection({ ...connection, database: undefined });
  try {
    if (schema !== 'marwis_sweet_e2e') throw new Error('Schema E2E tidak valid.');
    await admin.query(`CREATE DATABASE IF NOT EXISTS ${schema} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);
  } finally { await admin.end(); }
  for (const args of [['migrate:refresh', '--all', '-f'], ['db:seed', 'DevelopmentSeeder']]) {
    const result = spawnSync(process.env.E2E_PHP ?? 'php', ['spark', ...args], {
      cwd: root, env: environment(), encoding: 'utf8', windowsHide: true,
    });
    if (result.status !== 0 || /\[(?:Error|Exception)|Unable to connect|ErrorException/.test(result.stdout)) {
      throw new Error(`Setup ${args[0]} gagal: ${result.stdout}\n${result.stderr}`);
    }
  }
  clearThrottle();
  const browser = await chromium.launch();
  try {
    for (const role of ['owner', 'cashier'] as const) {
      const context = await browser.newContext({ baseURL });
      const page = await context.newPage();
      await authenticated(page, role);
      await context.storageState({ path: resolve(root, `e2e/.auth/${role}.json`) });
      await context.close();
    }
    const db = await mysql.createConnection(connection);
    const [rows] = await db.query<mysql.RowDataPacket[]>('SELECT DATABASE() name, COUNT(last_login_at) n FROM users');
    expect(rows[0].name).toBe(schema);
    expect(Number(rows[0].n)).toBe(2);
    await db.end();
  } finally { await browser.close(); }
}
