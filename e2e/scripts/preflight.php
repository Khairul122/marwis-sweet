<?php
$root = dirname(__DIR__, 2);
define('FCPATH', $root . '/public/');
define('ENVIRONMENT', 'development');
require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$database = config('Database');
$connection = $database->{$database->defaultGroup};
if ($connection['database'] !== 'marwis_sweet_e2e' || $connection['DBDriver'] !== 'MySQLi' || $connection['DBPrefix'] !== '') {
    throw new RuntimeException('E2E menolak konfigurasi database di luar marwis_sweet_e2e.');
}
echo json_encode(['schema' => $connection['database'], 'baseURL' => config('App')->baseURL, 'environment' => ENVIRONMENT]);
