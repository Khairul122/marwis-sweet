import { spawn, spawnSync } from 'node:child_process';
import { createWriteStream } from 'node:fs';
import { resolve } from 'node:path';
import { environment, root, prepareDirectories } from './env.mjs';

prepareDirectories();
const probe = spawnSync(process.env.E2E_PHP ?? 'php', ['e2e/scripts/preflight.php'], {
  cwd: root, env: environment({ CI_ENVIRONMENT: 'testing' }), encoding: 'utf8', windowsHide: true,
});
if (JSON.parse(probe.stdout).schema !== 'marwis_sweet_e2e') throw new Error('Reset PHPUnit ditolak.');
const reset = spawnSync(process.env.E2E_PHP ?? 'php', ['spark', 'migrate:refresh', '--all', '-f'], {
  cwd: root, env: environment({ CI_ENVIRONMENT: 'testing' }), encoding: 'utf8', windowsHide: true,
});
if (reset.status !== 0 || /\[[^\]\r\n]*(?:Exception|Error)\]|Fatal error:|Unable to connect/.test(reset.stdout + reset.stderr)) {
  process.stderr.write(reset.stdout + reset.stderr);
  throw new Error('Reset schema PHPUnit gagal.');
}
const log = createWriteStream(resolve(root, 'writable/e2e/phpunit.log'));
const child = spawn(process.env.E2E_PHP ?? 'php', [process.env.E2E_COMPOSER_PHAR ?? 'C:/ProgramData/ComposerSetup/bin/composer.phar', 'test', '--', '--no-coverage', ...process.argv.slice(2)], {
  cwd: root, env: environment({ CI_ENVIRONMENT: 'testing' }), windowsHide: true,
});
child.stdout.on('data', (chunk) => { process.stdout.write(chunk); log.write(chunk); });
child.stderr.on('data', (chunk) => { process.stderr.write(chunk); log.write(chunk); });
child.on('exit', (code) => { log.end(); process.exitCode = code ?? 1; });
