import { readFileSync, writeFileSync, mkdirSync, readdirSync, unlinkSync } from 'node:fs';
import { resolve } from 'node:path';

export const root = resolve(process.cwd());
export const schema = 'marwis_sweet_e2e';
export const baseURL = 'http://localhost:8081/';
const local = {};
try {
  for (const line of readFileSync(resolve(root, '.env'), 'utf8').split(/\r?\n/)) {
    const match = line.match(/^\s*([\w.]+)\s*=\s*(.*?)\s*$/);
    if (match) local[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
  }
} catch (error) {
  if (error.code !== 'ENOENT') throw error;
}
const setting = (name, fallback) => process.env[name] ?? local[name] ?? fallback;
export const connection = {
  host: process.env.E2E_DB_HOST ?? setting('database.default.hostname', '127.0.0.1'),
  port: Number(process.env.E2E_DB_PORT ?? setting('database.default.port', 3306)),
  user: process.env.E2E_DB_USER ?? setting('database.default.username', 'root'),
  password: process.env.E2E_DB_PASSWORD ?? setting('database.default.password', ''),
  database: schema,
  decimalNumbers: true,
};

export function environment() {
  const env = { ...process.env, CI_ENVIRONMENT: 'development', MARWIS_E2E: '1',
    PHP_INI_SCAN_DIR: resolve(root, 'writable/e2e/php'),
    'app.baseURL': baseURL, 'app.forceGlobalSecureRequests': 'false',
    'app.indexPage': '',
    'session.cookieName': 'marwis_e2e_session',
    'session.savePath': resolve(root, 'writable/e2e/session'),
    'session.timeToUpdate': '0',
    'cache.file.storePath': resolve(root, 'writable/e2e/cache') + '/',
    'cache.prefix': 'e2e_',
    SEED_OWNER_NAME: ' ', SEED_OWNER_USERNAME: ' ', SEED_OWNER_PASSWORD: ' ',
  };
  for (const group of ['default', 'tests']) {
    Object.assign(env, {
      [`database.${group}.DSN`]: '',
      [`database.${group}.hostname`]: connection.host,
      [`database.${group}.port`]: String(connection.port),
      [`database.${group}.username`]: connection.user,
      [`database.${group}.password`]: connection.password,
      [`database.${group}.database`]: schema,
      [`database.${group}.DBDriver`]: 'MySQLi',
      [`database.${group}.DBPrefix`]: '',
    });
  }
  env.MARWIS_E2E_KEYS = Object.keys(env).filter((key) => key.includes('.') || key.startsWith('SEED_') || key === 'CI_ENVIRONMENT').join('|');
  return env;
}

export function prepareDirectories() {
  for (const path of ['writable/e2e/cache', 'writable/e2e/session', 'writable/e2e/php', 'e2e/.auth']) {
    mkdirSync(resolve(root, path), { recursive: true });
  }
  writeFileSync(resolve(root, 'writable/e2e/php/e2e.ini'),
    `auto_prepend_file="${resolve(root, 'e2e/scripts/bootstrap.php').replaceAll('\\', '/')}"\n`);
}

export function clearThrottle() {
  const cache = resolve(root, 'writable/e2e/cache');
  for (const entry of readdirSync(cache, { withFileTypes: true })) {
    if (entry.isFile() && entry.name !== 'index.html') unlinkSync(resolve(cache, entry.name));
  }
}
