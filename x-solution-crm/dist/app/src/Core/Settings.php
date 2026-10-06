<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Persistente Schlüssel/Wert-Ablage in der Tabelle app_settings
 * (z. B. Zeitstempel des letzten Cron-Laufs). Keine Zugangsdaten!
 */
final class Settings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $v = Database::value('SELECT `value` FROM app_settings WHERE `key` = ?', [$key]);
        return $v === null ? $default : (string) $v;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO app_settings (`key`, `value`, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
            [$key, $value]
        );
    }
}
