<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // secure-Flag immer, sobald die Seite über HTTPS läuft oder APP_URL https ist.
        $secure = Request::isHttps() || str_starts_with((string) Env::get('APP_URL', ''), 'https://');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_name('XCRMSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (Request::basePath() ?: '') . '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}
