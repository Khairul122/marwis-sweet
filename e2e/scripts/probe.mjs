import { spawnSync } from 'node:child_process';
import mysql from 'mysql2/promise';
import { root, environment, connection, prepareDirectories } from './env.mjs';
prepareDirectories();
for (const args of [['-d', `auto_prepend_file=${root}/e2e/scripts/bootstrap.php`, 'e2e/scripts/env-probe.php'], ['-d', `auto_prepend_file=${root}/e2e/scripts/bootstrap.php`, 'e2e/scripts/preflight.php']]) {
  const result = spawnSync('php', args, { env: environment(), cwd: root, encoding: 'utf8' });
  console.log(result.stdout, result.stderr);
}
const db = await mysql.createConnection(connection);
const [rows] = await db.query(`SELECT table_schema,table_name,table_rows FROM information_schema.tables
  WHERE table_schema IN ('marwis_sweet_e2e','marwis_sweet') AND table_name IN ('users','sales','migrations')`);
console.log(rows);
for (const query of ['SHOW VARIABLES WHERE Variable_name IN (\'log_bin\',\'datadir\',\'binlog_expire_logs_seconds\')', 'SHOW BINARY LOGS']) {
  try { console.log((await db.query(query))[0]); } catch (error) { console.log(error.message); }
}
await db.end();
