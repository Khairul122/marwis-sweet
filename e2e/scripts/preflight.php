<?php
$root = dirname(__DIR__, 2);
define('FCPATH', $root . '/public/');
define('ENVIRONMENT', getenv('CI_ENVIRONMENT') ?: 'development');
require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
if (PHP_SAPI === 'cli') {
    CodeIgniter\Boot::bootConsole($paths);
} else {
    class E2EConfigurationBoot extends CodeIgniter\Boot
    {
        public static function configure(Config\Paths $paths): void
        {
            static::definePathConstants($paths);
            static::loadConstants();
            static::loadDotEnv($paths);
            static::loadEnvironmentBootstrap($paths);
            static::loadCommonFunctions();
            static::loadAutoloader();
        }
    }
    E2EConfigurationBoot::configure($paths);
}
$database = config('Database');
$connection = $database->{$database->defaultGroup};
if ($connection['database'] !== 'marwis_sweet_e2e' || $connection['DBDriver'] !== 'MySQLi' || $connection['DBPrefix'] !== '') {
    throw new RuntimeException('E2E menolak konfigurasi database di luar marwis_sweet_e2e.');
}
echo json_encode(['schema' => db_connect()->getDatabase(), 'baseURL' => config('App')->baseURL, 'environment' => ENVIRONMENT,
    'csrf' => in_array('csrf', config('Filters')->globals['before'], true)]);
