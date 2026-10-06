<?php
/**
 * X-Solution CRM – Front-Controller. Alle Seiten laufen über diese Datei.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Env;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Services\Installer;

send_security_headers();

if (!Env::exists() || !Installer::isLocked()) {
    http_response_code(503);
    View::render('errors/not_installed', ['pageTitle' => 'Nicht installiert'], 'layout/guest');
    exit;
}

Session::start();

$router = new Router();
require APP_PATH . '/routes.php';

try {
    $router->dispatch(Request::method(), Request::path());
} catch (\Throwable $e) {
    log_message('app', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $msg = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)
        ? $e->getMessage()
        : 'Es ist ein unerwarteter Fehler aufgetreten. Details stehen im Fehlerprotokoll (storage/logs).';
    View::error(500, 'Fehler', $msg);
}
