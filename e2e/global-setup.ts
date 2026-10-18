import { chromium, expect } from '@playwright/test';
import mysql from 'mysql2/promise';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { root, schema, connection, baseURL, environment, prepareDirectories, clearThrottle } from './scripts/env.mjs';
import { authenticated } from './helpers/auth';

export default async function setup() {
  prepareDirectories();
  const admin = await mysql.createConnection({ ...connection, database: undefined });
  try {
    if (schema !== 'marwis_sweet_e2e') throw new Error('Schema E2E tidak valid.');
    await admin.query(`CREATE DATABASE IF NOT EXISTS ${schema} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);
  } finally { await admin.end(); }
  const probe = spawnSync(process.env.E2E_PHP ?? 'php', ['e2e/scripts/preflight.php'], {
    cwd: root, env: environment({ CI_ENVIRONMENT: 'testing' }), encoding: 'utf8', windowsHide: true,
  });
  const configurations = [JSON.parse(probe.stdout), await (await fetch(`${baseURL}__e2e/health`)).json()];
  for (const configuration of configurations) {
    if (configuration.schema !== schema || configuration.baseURL !== baseURL || configuration.csrf !== true) {
      throw new Error(`Preflight CLI/HTTP gagal; reset dibatalkan (schema=${configuration.schema}, baseURL=${configuration.baseURL}, csrf=${configuration.csrf}).`);
    }
  }
  const commands = [['migrate:refresh', '--all', '-f'], ['db:seed', 'DevelopmentSeeder']];
  if (process.env.E2E_EVALUATION === '1') commands.push(['db:seed', 'EvaluationDataSeeder']);
  for (const args of commands) {
    const result = spawnSync(process.env.E2E_PHP ?? 'php', ['spark', ...args], {
      cwd: root, env: environment({ CI_ENVIRONMENT: 'testing' }), encoding: 'utf8', windowsHide: true,
    });
    if (result.status !== 0 || /\[[^\]\r\n]*(?:Exception|Error)\]|Fatal error:|Unable to connect/.test(result.stdout + result.stderr)) {
      throw new Error(`Setup ${args[0]} gagal: ${result.stdout}\n${result.stderr}`);
    }
    if (args[0] === 'migrate:refresh') {
      const verify = await mysql.createConnection(connection);
      try {
        const [rows] = await verify.query<mysql.RowDataPacket[]>('SELECT (SELECT COUNT(*) FROM sales)+(SELECT COUNT(*) FROM users)+(SELECT COUNT(*) FROM journal_entries) AS remaining');
        expect(Number(rows[0].remaining), 'Reset E2E harus menghasilkan tabel transaksi dan user kosong.').toBe(0);
      } finally { await verify.end(); }
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
