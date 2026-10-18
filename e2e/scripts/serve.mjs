import { spawn } from 'node:child_process';
import { openSync } from 'node:fs';
import { resolve } from 'node:path';
import { root, environment, prepareDirectories } from './env.mjs';

prepareDirectories();
const log = openSync(resolve(root, 'writable/e2e/server.log'), 'a');
const child = spawn(process.env.E2E_PHP ?? 'php', ['-d', `auto_prepend_file=${root}/e2e/scripts/bootstrap.php`,
  '-S', 'localhost:8081', '-t', 'public', 'e2e/scripts/router.php'], {
  cwd: root, env: environment(), stdio: ['ignore', log, log], windowsHide: true,
});
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => child.kill(signal));
child.on('exit', (code) => process.exit(code ?? 1));
