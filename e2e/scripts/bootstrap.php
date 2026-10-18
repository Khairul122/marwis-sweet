<?php
if (getenv('MARWIS_E2E') !== '1') {
    throw new RuntimeException('Bootstrap ini khusus proses E2E.');
}
// The development toolbar races over shared history files when the test uses two PHP servers.
if (PHP_SAPI === 'cli-server' && !defined('CI_DEBUG')) {
    define('CI_DEBUG', false);
}
$overrides = json_decode(getenv('MARWIS_E2E_CONFIG') ?: '', true, 512, JSON_THROW_ON_ERROR);
if (($overrides['database.default.database'] ?? '') !== 'marwis_sweet_e2e'
    || ($overrides['database.tests.database'] ?? '') !== 'marwis_sweet_e2e') {
    throw new RuntimeException('Bootstrap E2E menolak schema selain marwis_sweet_e2e.');
}
foreach ($overrides as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}
if (($overrides['CI_ENVIRONMENT'] ?? '') === 'testing' && !defined('SUPPORTPATH')) {
    define('SUPPORTPATH', dirname(__DIR__, 2) . '/tests/_support/');
}
