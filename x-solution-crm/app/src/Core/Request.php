<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** Basis-Pfad, falls die App in einem Unterordner läuft (z. B. /crm). */
    public static function basePath(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        return $base = ($dir === '.' ? '' : $dir);
    }

    public static function path(): string
    {
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        return '/' . trim(rawurldecode($uri), '/');
    }

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = self::input($key, $default);
        return is_string($v) ? $v : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
