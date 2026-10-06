<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::one('SELECT * FROM users WHERE email = ? AND active = 1', [mb_strtolower(trim($email))]);
        if ($user === null) {
            // Gleiche Rechenzeit wie bei existierendem Benutzer (kein E-Mail-Raten über Antwortzeiten).
            password_verify($password, password_hash('dummy', PASSWORD_DEFAULT));
            return false;
        }
        $hash = (string) $user['password_hash'];
        if (!password_verify($password, $hash)) {
            return false;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['login_at'] = time();
        unset($_SESSION['_csrf']);
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        self::$user = $user;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        self::$user = null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        self::$user = Database::one('SELECT id, name, email, role, active, created_at, last_login_at FROM users WHERE id = ? AND active = 1', [(int) $id]);
        if (self::$user === null) {
            unset($_SESSION['user_id']);
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }
}
