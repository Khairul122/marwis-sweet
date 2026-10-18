import { spawn } from 'node:child_process';
import { createReadStream, createWriteStream, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { resolve } from 'node:path';
import mysql from 'mysql2/promise';
import { root, connection } from './env.mjs';

const bin = 'D:/Alternatif_D/laragon/bin/mysql/mysql-8.4.3-winx64/bin';
const source = { host: '127.0.0.1', port: 3307, user: 'root', database: 'marwis_sweet', decimalNumbers: true };
const target = { ...connection, database: 'marwis_sweet' };
const file = (name) => resolve(root, `writable/e2e/recovery/${name}`);
async function dump(config, name) {
  const child = spawn(`${bin}/mysqldump.exe`, ['--no-defaults', `--host=${config.host}`, `--port=${config.port}`,
    `--user=${config.user}`, '--single-transaction', '--routines', '--triggers', '--no-tablespaces', `--result-file=${file(name)}`, 'marwis_sweet'],
    { env: { ...process.env, MYSQL_PWD: config.password ?? '' }, windowsHide: true });
  const errors = createWriteStream(file(`${name}.log`));
  child.stderr.pipe(errors);
  if (await new Promise((done) => child.on('exit', done)) !== 0) throw new Error('Dump gagal.');
}
async function fingerprint(db) {
  const [tables] = await db.query('SHOW TABLES');
  const result = {};
  for (const row of tables) {
    const table = Object.values(row)[0];
    const [data] = await db.query(`SELECT * FROM \`${table}\``);
    result[table] = { count: data.length, sha256: createHash('sha256').update(JSON.stringify(data.map((item) => JSON.stringify(item)).sort())).digest('hex') };
  }
  return result;
}
const recovered = await mysql.createConnection(source);
const live = await mysql.createConnection(target);
if (Number((await recovered.query('SELECT @@port n'))[0][0].n) !== 3307 || Number((await live.query('SELECT @@port n'))[0][0].n) !== Number(connection.port)) {
  throw new Error('Instance pemulihan tidak sesuai.');
}
const [unbalanced] = await recovered.query(`SELECT e.id FROM journal_entries e JOIN journal_lines l ON l.journal_entry_id=e.id
  WHERE e.status='posted' GROUP BY e.id HAVING SUM(l.debit) <> SUM(l.credit)`);
if (unbalanced.length) throw new Error('Jurnal rekonstruksi tidak seimbang.');
const before = await fingerprint(recovered);
if (before.sales.count !== 156 || before.migrations.count !== 30) throw new Error('Hasil rekonstruksi tidak sesuai audit.');
await dump(target, 'post-incident.sql');
await dump(source, 'before-incident.sql');
await live.end();
const child = spawn(`${bin}/mysql.exe`, ['--no-defaults', `--host=${target.host}`, `--port=${target.port}`,
  `--user=${target.user}`, '--binary-mode', 'marwis_sweet'], { env: { ...process.env, MYSQL_PWD: target.password }, windowsHide: true });
child.stdin.on('error', () => {});
createReadStream(file('before-incident.sql')).pipe(child.stdin);
const errors = createWriteStream(file('restore.log'));
child.stderr.pipe(errors);
if (await new Promise((done) => child.on('exit', done)) !== 0) throw new Error('Restore gagal; periksa log privat.');
const restored = await mysql.createConnection(target);
const after = await fingerprint(restored);
if (JSON.stringify(before) !== JSON.stringify(after)) throw new Error('Fingerprint database tidak cocok.');
writeFileSync(file('verification.json'), JSON.stringify({ cutoff: '2026-10-18 19:58:04 Asia/Jakarta', verifiedAt: new Date().toISOString(), tables: after }, null, 2));
console.log(`Pemulihan terverifikasi: ${Object.keys(after).length} tabel; setiap jumlah dan SHA-256 baris cocok dengan rekonstruksi sebelum kejadian.`);
console.log('sales:', after.sales.count, 'sale_items:', after.sale_items.count, 'journal_entries:', after.journal_entries.count);
await restored.end(); await recovered.end();
