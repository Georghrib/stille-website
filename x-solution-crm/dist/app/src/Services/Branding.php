<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;

/**
 * Logo und Anzeigename (Einstellungen → Erscheinungsbild).
 * Das Logo liegt in storage/branding/ und wird über die Route /branding/logo ausgeliefert.
 */
final class Branding
{
    public const DEFAULT_NAME = 'X-Solution';
    public const MAX_BYTES = 1_048_576;
    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    private static ?array $cache = null;

    /** @return array{name:string, logo:?string, mime:?string, version:string} */
    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        if (!Installer::isLocked()) {
            return self::$cache = ['name' => self::DEFAULT_NAME, 'logo' => null, 'mime' => null, 'version' => '1'];
        }
        try {
            $name = Settings::get('brand_name');
            $logo = Settings::get('brand_logo');
            $mime = Settings::get('brand_logo_mime');
            $version = (string) Settings::get('brand_logo_version', '1');
        } catch (\Throwable) {
            $name = $logo = $mime = null; // z. B. während der Installation
            $version = '1';
        }
        if ($logo && !is_file(STORAGE_PATH . '/' . $logo)) {
            $logo = null;
        }
        return self::$cache = ['name' => $name ?? self::DEFAULT_NAME, 'logo' => $logo, 'mime' => $logo ? $mime : null, 'version' => $version];
    }

    public static function name(): string
    {
        return self::get()['name'];
    }

    public static function logoUrl(): ?string
    {
        $b = self::get();
        return $b['logo'] ? url('/branding/logo', ['v' => $b['version']]) : null;
    }

    /** HTML für Sidebar/Login: eigenes Logo oder das Standard-X. */
    public static function html(): string
    {
        $name = self::name();
        $logo = self::logoUrl();
        $mark = $logo
            ? '<img class="logo-img" src="' . e($logo) . '" alt="' . e($name !== '' ? $name : 'Logo') . '">'
            : '<span class="logo-mark">' . icon('logo') . '</span>';
        return $mark . ($name !== '' ? '<span class="logo-text">' . e($name) . '</span>' : '');
    }

    public static function faviconTag(): string
    {
        $b = self::get();
        if ($b['logo']) {
            return '<link rel="icon" href="' . e((string) self::logoUrl()) . '" type="' . e((string) $b['mime']) . '">';
        }
        return '<link rel="icon" href="' . e(asset('img/favicon.svg')) . '" type="image/svg+xml">';
    }

    /**
     * Prüft und speichert ein hochgeladenes Logo.
     * @throws \RuntimeException mit deutscher Fehlermeldung
     */
    public static function storeUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new \RuntimeException(match ($file['error'] ?? null) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist zu groß (max. 1 MB).',
                UPLOAD_ERR_NO_FILE => 'Bitte eine Datei auswählen.',
                default => 'Der Upload ist fehlgeschlagen.',
            });
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('Die Datei ist zu groß (max. 1 MB).');
        }
        $bytes = (string) file_get_contents((string) $file['tmp_name']);
        $mime = self::detect($bytes);
        if ($mime === null) {
            throw new \RuntimeException('Bitte ein Bild im Format PNG, JPG, WebP oder SVG hochladen.');
        }
        $dir = STORAGE_PATH . '/branding';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new \RuntimeException('Der Ordner storage/branding konnte nicht angelegt werden.');
        }
        foreach (glob($dir . '/logo.*') ?: [] as $old) {
            @unlink($old);
        }
        $rel = 'branding/logo.' . self::TYPES[$mime];
        if (@file_put_contents(STORAGE_PATH . '/' . $rel, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException('Das Logo konnte nicht gespeichert werden.');
        }
        Settings::set('brand_logo', $rel);
        Settings::set('brand_logo_mime', $mime);
        Settings::set('brand_logo_version', (string) time());
        self::$cache = null;
    }

    public static function removeLogo(): void
    {
        foreach (glob(STORAGE_PATH . '/branding/logo.*') ?: [] as $old) {
            @unlink($old);
        }
        Settings::set('brand_logo', null);
        Settings::set('brand_logo_mime', null);
        self::$cache = null;
    }

    /** Erkennt den Bildtyp am Inhalt (nicht an der Dateiendung). */
    private static function detect(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        $head = strtolower(ltrim(substr($bytes, 0, 2048), "\xEF\xBB\xBF \t\r\n"));
        if ((str_starts_with($head, '<svg') || str_starts_with($head, '<?xml')) && str_contains(strtolower($bytes), '<svg')) {
            // Aktive Inhalte ablehnen (zusätzlich wird SVG mit sandbox-CSP ausgeliefert)
            if (preg_match('/<script|on[a-z]+\s*=|javascript:|<foreignobject|<iframe|<embed|<object/i', $bytes)) {
                return null;
            }
            return 'image/svg+xml';
        }
        return null;
    }

    /** Auslieferung für die Route /branding/logo (öffentlich, z. B. für die Login-Seite). */
    public static function serve(): never
    {
        $b = self::get();
        $file = $b['logo'] ? realpath(STORAGE_PATH . '/' . $b['logo']) : false;
        $base = realpath(STORAGE_PATH . '/branding');
        if (!$file || !$base || !str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $b['mime']);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        readfile($file);
        exit;
    }
}
