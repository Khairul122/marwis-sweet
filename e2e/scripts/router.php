<?php
require __DIR__ . '/bootstrap.php';
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/__e2e/health') {
    header('Content-Type: application/json');
    require __DIR__ . '/preflight.php';
    return true;
}
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/__e2e/login-intended') {
    ob_start();
    require __DIR__ . '/preflight.php';
    ob_end_clean();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || config('Session')->cookieName !== 'marwis_e2e_session') {
        throw new RuntimeException('Fixture sesi E2E ditolak.');
    }
    $request = service('request');
    service('security')->verify($request);
    session()->set('login_intended', (string) $request->getPost('target'));
    session_write_close();
    service('response')->setJSON(['stored' => true])->send();
    return true;
}
return require dirname(__DIR__, 2) . '/vendor/codeigniter4/framework/system/rewrite.php';
