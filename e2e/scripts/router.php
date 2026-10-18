<?php
require __DIR__ . '/bootstrap.php';
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/__e2e/health') {
    header('Content-Type: application/json');
    require __DIR__ . '/preflight.php';
    return true;
}
return require dirname(__DIR__, 2) . '/vendor/codeigniter4/framework/system/rewrite.php';
