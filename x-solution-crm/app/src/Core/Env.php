<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimaler .env-Parser (KEY=VALUE, # Kommentare, optionale Anführungszeichen).
 * Werte werden nur intern gehalten und nicht in getenv()/putenv() gespiegelt.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];
    private static ?string $file = null;

    public static function load(string $file): void
    {
        self::$file = $file;
        self::$values = is_file($file) ? self::parse((string) file_get_contents($file)) : [];
    }

    public static function exists(): bool
    {
        return self::$file !== null && is_file(self::$file);
    }

    public static function path(): string
    {
        return self::$file ?? BASE_PATH . '/.env';
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::$values[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    /** @return array<string,string> */
    public static function parse(string $content): array
    {
        $values = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $quote = $value[0];
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
                }
            } elseif (($pos = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $pos));
            }
            if (preg_match('/^[A-Z0-9_]+$/i', $key)) {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    public static function format(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_\-.:\/@+]+$/', $value)) {
            return $value;
        }
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    /**
     * Ersetzt bzw. ergänzt Schlüssel in der .env-Datei (atomar über Temp-Datei).
     *
     * @param array<string,string> $changes
     */
    public static function update(array $changes, ?string $file = null): void
    {
        $file ??= self::path();
        $lines = is_file($file) ? (preg_split('/\R/', (string) file_get_contents($file)) ?: []) : [];
        $done = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/i', $line, $m) && array_key_exists($m[1], $changes)) {
                $lines[$i] = $m[1] . '=' . self::format($changes[$m[1]]);
                $done[$m[1]] = true;
            }
        }
        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }
        foreach ($changes as $key => $value) {
            if (!isset($done[$key])) {
                $lines[] = $key . '=' . self::format($value);
            }
        }
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, implode("\n", $lines) . "\n", LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Die .env-Datei ist nicht beschreibbar: ' . $file);
        }
        @chmod($file, 0640);
        if ($file === self::path()) {
            self::load($file);
        }
    }
}
