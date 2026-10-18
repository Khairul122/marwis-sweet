import { spawn } from 'node:child_process';
import { createReadStream, createWriteStream, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import mysql from 'mysql2/promise';
import { root } from './env.mjs';

const bin = 'D:/Alternatif_D/laragon/bin/mysql/mysql-8.4.3-winx64/bin';
const db = await mysql.createConnection({ host: '127.0.0.1', port: 3307, user: 'root' });
if (Number((await db.query('SELECT @@port n'))[0][0].n) !== 3307) throw new Error('Bukan instance pemulihan.');
await db.query('DROP DATABASE IF EXISTS marwis_sweet');
await db.query('CREATE DATABASE marwis_sweet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
const replay = readFileSync(resolve(root, 'writable/e2e/recovery/replay.sql'), 'utf8');
for (const name of new Set([...replay.matchAll(/`(marwis_sweet_[a-zA-Z0-9_]+)`/g)].map((match) => match[1]))) {
  await db.query(`CREATE DATABASE IF NOT EXISTS \`${name}\``);
}
await db.end();
async function importFile(path, label) {
  const log = createWriteStream(resolve(root, `writable/e2e/recovery/${label}.log`));
  const child = spawn(`${bin}/mysql.exe`, ['--no-defaults', '--host=127.0.0.1', '--port=3307', '--user=root', '--binary-mode', 'marwis_sweet'], { windowsHide: true });
  createReadStream(resolve(root, path)).pipe(child.stdin);
  child.stdin.on('error', () => {});
  child.stdout.pipe(log); child.stderr.pipe(log);
  const code = await new Promise((done) => child.on('exit', done));
  if (code !== 0) throw new Error(`${label} gagal; periksa log privat.`);
  console.log(`${label} selesai`);
}
await importFile('writable/backups/pre-clean-20261013-203138.sql', 'backup');
await importFile('writable/e2e/recovery/replay.sql', 'replay');
const recovered = await mysql.createConnection({ host: '127.0.0.1', port: 3307, user: 'root', database: 'marwis_sweet' });
for (const table of ['users', 'products', 'sales', 'sale_items', 'refunds', 'journal_entries', 'goods_receipts', 'expenses', 'inventory_items', 'audit_events', 'migrations']) {
  console.log(table, (await recovered.query(`SELECT COUNT(*) n FROM ${table}`))[0][0].n);
}
await recovered.end();
