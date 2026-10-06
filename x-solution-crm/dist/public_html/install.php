<?php
/**
 * X-Solution CRM – Browser-Installation.
 * 1. legt (falls nötig) die .env an, 2. erstellt die Tabellen, 3. legt den ersten Admin an,
 * 4. optional Demodaten, 5. sperrt sich über storage/install.lock.
 * Danach diese Datei bitte löschen.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Core\View;
use App\Services\DemoData;
use App\Services\Installer;

send_security_headers();
Session::start();

$render = static function (string $step, array $data = []): never {
    View::render('install/install', $data + ['step' => $step, 'pageTitle' => 'Installation'], 'layout/guest');
    exit;
};

if (Installer::isLocked()) {
    http_response_code(403);
    $render('locked');
}

$errors = [];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !Csrf::valid($_POST['_csrf'] ?? null)) {
    $errors[] = 'Sitzung abgelaufen – bitte erneut absenden.';
    $isPost = false;
}

$detectedUrl = (\App\Core\Request::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . \App\Core\Request::basePath();

/* ---------- Schritt 1: .env anlegen ---------- */
if (!Env::exists()) {
    $form = [
        'DB_HOST' => trim((string) ($_POST['DB_HOST'] ?? 'localhost')),
        'DB_NAME' => trim((string) ($_POST['DB_NAME'] ?? '')),
        'DB_USER' => trim((string) ($_POST['DB_USER'] ?? '')),
        'DB_PASS' => (string) ($_POST['DB_PASS'] ?? ''),
        'APP_URL' => rtrim(trim((string) ($_POST['APP_URL'] ?? $detectedUrl)), '/'),
    ];
    $envContent = null;
    if ($isPost && ($_POST['action'] ?? '') === 'env') {
        if ($form['DB_NAME'] === '' || $form['DB_USER'] === '') {
            $errors[] = 'Bitte Datenbankname und Benutzer angeben.';
        } elseif (!filter_var($form['APP_URL'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Bitte eine gültige APP_URL angeben (z. B. https://crm.example.at).';
        } elseif ($err = Installer::testConnection(['host' => $form['DB_HOST'], 'port' => '3306', 'name' => $form['DB_NAME'], 'user' => $form['DB_USER'], 'pass' => $form['DB_PASS']])) {
            $errors[] = 'Datenbankverbindung fehlgeschlagen: ' . $err;
        } else {
            $values = $form + [
                'EASYBILL_API_KEY' => '',
                'WEBHOOK_SECRET' => bin2hex(random_bytes(24)),
                'CRON_SECRET' => bin2hex(random_bytes(24)),
            ];
            try {
                Env::update($values, BASE_PATH . '/.env');
                redirect('install.php');
            } catch (\RuntimeException) {
                $envContent = '';
                foreach ($values as $k => $v) {
                    $envContent .= $k . '=' . Env::format($v) . "\n";
                }
            }
        }
    }
    $render('env', ['errors' => $errors, 'form' => $form, 'envContent' => $envContent, 'requirements' => Installer::requirements()]);
}

/* ---------- Schritt 2: Tabellen + Admin ---------- */
$dbError = null;
try {
    Database::pdo()->query('SELECT 1');
} catch (\PDOException $e) {
    $dbError = $e->getMessage();
}

$form = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'demo' => $isPost ? isset($_POST['demo']) : true,
];

if ($isPost && ($_POST['action'] ?? '') === 'install' && $dbError === null) {
    $password = (string) ($_POST['password'] ?? '');
    if ($form['name'] === '') {
        $errors[] = 'Bitte einen Namen angeben.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }
    if (mb_strlen($password) < 10) {
        $errors[] = 'Das Passwort muss mindestens 10 Zeichen lang sein.';
    } elseif ($password !== (string) ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }
    if ($errors === []) {
        try {
            Installer::createSchema(Database::pdo());
            if (Installer::hasUsers()) {
                Installer::lock();
                $errors[] = 'In der Datenbank existieren bereits Benutzer – die Installation wurde gesperrt. Bitte install.php löschen und normal anmelden.';
            } else {
                $userId = Database::transaction(static function () use ($form, $password) {
                    $id = Installer::createAdmin($form['name'], $form['email'], $password);
                    if ($form['demo']) {
                        DemoData::seed($id);
                    }
                    return $id;
                });
                \App\Core\Settings::set('installed_at', date('c'));
                $locked = Installer::lock();
                $appUrl = rtrim((string) env('APP_URL', $detectedUrl), '/');
                $render('done', [
                    'locked' => $locked,
                    'loginUrl' => $appUrl . '/login',
                    'cronUrl' => $appUrl . '/cron.php?secret=' . (string) env('CRON_SECRET', ''),
                    'webhookUrl' => $appUrl . '/easybill-webhook.php?secret=' . (string) env('WEBHOOK_SECRET', ''),
                    'demo' => $form['demo'],
                    'userId' => $userId,
                ]);
            }
        } catch (\Throwable $e) {
            log_message('install', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $errors[] = 'Installation fehlgeschlagen: ' . $e->getMessage();
        }
    }
}

$render('admin', [
    'errors' => $errors,
    'form' => $form,
    'dbError' => $dbError,
    'requirements' => Installer::requirements(),
    'missingSecrets' => array_values(array_filter(['WEBHOOK_SECRET', 'CRON_SECRET', 'APP_URL'], fn ($k) => env($k) === null)),
]);
