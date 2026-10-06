<?php
/**
 * X-Solution CRM – Front-Controller. Alle Seiten laufen über diese Datei.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
if (!is_file(BASE_PATH . '/app/bootstrap.php')) {
    // Häufigster Upload-Fehler: app/, config/, storage/ liegen nicht NEBEN public_html
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("X-Solution CRM: Ordner app/ nicht gefunden.\n\n"
        . "Erwartet: " . BASE_PATH . "/app/bootstrap.php\n"
        . "Diese Datei liegt in: " . __DIR__ . "\n\n"
        . "Bitte app/, config/, storage/ und .env.example in den Ordner " . BASE_PATH . " hochladen,\n"
        . "also eine Ebene ÜBER public_html (nicht hinein).\n");
}
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
