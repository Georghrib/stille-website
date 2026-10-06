<?php
/**
 * X-Solution CRM – zentraler Bootstrap.
 *
 * Wird von allen Einstiegsdateien in public_html eingebunden:
 *   require dirname(__DIR__) . '/app/bootstrap.php';
 *
 * BASE_PATH zeigt immer auf den Domain-Ordner (eine Ebene über public_html),
 * in dem app/, config/, storage/ und .env liegen.
 */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('VIEW_PATH', APP_PATH . '/views');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('X-Solution CRM benötigt PHP 8.1 oder neuer (empfohlen: 8.3).');
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_PATH . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');

date_default_timezone_set((string) config('timezone', 'Europe/Vienna'));
mb_internal_encoding('UTF-8');

// Fehler nie im Browser anzeigen, sondern nach storage/logs schreiben.
$debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(STORAGE_PATH . '/logs') && is_writable(STORAGE_PATH . '/logs')) {
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
}
error_reporting(E_ALL);
