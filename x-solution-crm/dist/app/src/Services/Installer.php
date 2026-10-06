<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class Installer
{
    public const LOCK_FILE = '/install.lock';

    public static function lockPath(): string
    {
        return STORAGE_PATH . self::LOCK_FILE;
    }

    public static function isLocked(): bool
    {
        return is_file(self::lockPath());
    }

    public static function lock(): bool
    {
        return @file_put_contents(self::lockPath(), 'installiert am ' . date('c') . "\n") !== false;
    }

    /** @return array<int,array{label:string,ok:bool,hint:string}> */
    public static function requirements(): array
    {
        $checks = [];
        $checks[] = ['label' => 'PHP-Version ' . PHP_VERSION . ' (empfohlen 8.3)', 'ok' => PHP_VERSION_ID >= 80100, 'hint' => 'Im Hosting-Panel unter „PHP-Konfiguration“ PHP 8.3 auswählen.'];
        foreach (['pdo_mysql', 'curl', 'mbstring', 'openssl', 'zip'] as $ext) {
            $checks[] = ['label' => 'PHP-Erweiterung ' . $ext, 'ok' => extension_loaded($ext), 'hint' => 'In der PHP-Konfiguration des Hostings aktivieren.'];
        }
        foreach (['', '/pdfs', '/logs'] as $dir) {
            $path = STORAGE_PATH . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0750, true);
            }
            $checks[] = ['label' => 'Ordner storage' . $dir . ' beschreibbar', 'ok' => is_dir($path) && is_writable($path), 'hint' => 'Im Dateimanager Berechtigung 755 (bzw. 775) für ' . 'storage' . $dir . ' setzen.'];
        }
        $checks[] = ['label' => 'Ordner app/ und config/ außerhalb von public_html', 'ok' => is_file(APP_PATH . '/bootstrap.php') && is_file(CONFIG_PATH . '/schema.sql'), 'hint' => 'app/ und config/ müssen neben public_html liegen.'];
        return $checks;
    }

    public static function testConnection(array $cfg): ?string
    {
        try {
            Database::connect($cfg)->query('SELECT 1');
            return null;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public static function createSchema(PDO $pdo): void
    {
        $sql = (string) file_get_contents(CONFIG_PATH . '/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        foreach (preg_split('/;\s*(\R|$)/', $sql) ?: [] as $stmt) {
            if (trim($stmt) !== '') {
                $pdo->exec($stmt);
            }
        }
    }

    public static function hasUsers(): bool
    {
        try {
            return (int) Database::value('SELECT COUNT(*) FROM users') > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    public static function createAdmin(string $name, string $email, string $password): int
    {
        return Database::insert('users', [
            'name' => $name,
            'email' => mb_strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
    }
}
