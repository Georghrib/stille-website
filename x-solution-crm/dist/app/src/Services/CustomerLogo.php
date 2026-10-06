<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Kundenlogos: storage/customer-logos/{kunden-id}.{png|jpg|webp|svg}
 * Ausgeliefert nur nach Login über /kunden/{id}/logo.
 */
final class CustomerLogo
{
    private const EXT_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];

    private static function dir(): string
    {
        return STORAGE_PATH . '/customer-logos';
    }

    /** Pfad der Logo-Datei oder null. */
    public static function file(int $customerId): ?string
    {
        foreach (array_keys(self::EXT_MIME) as $ext) {
            $f = self::dir() . '/' . $customerId . '.' . $ext;
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }

    public static function url(int $customerId): ?string
    {
        $f = self::file($customerId);
        return $f ? url('/kunden/' . $customerId . '/logo', ['v' => (string) filemtime($f)]) : null;
    }

    /** Logo oder Initialen-Avatar. */
    public static function avatar(array $customer, string $class = 'avatar'): string
    {
        $u = self::url((int) $customer['id']);
        if ($u) {
            return '<span class="' . e($class) . ' avatar-logo"><img src="' . e($u) . '" alt="" loading="lazy"></span>';
        }
        return '<span class="' . e($class) . '">' . e(initials((string) $customer['name'])) . '</span>';
    }

    /** @throws \RuntimeException mit deutscher Meldung */
    public static function store(int $customerId, array $file): void
    {
        $bytes = Branding::readUpload($file);
        $mime = Branding::detectImage($bytes);
        if ($mime === null) {
            throw new \RuntimeException('Bitte ein Bild im Format PNG, JPG, WebP oder SVG hochladen.');
        }
        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0750, true)) {
            throw new \RuntimeException('Der Ordner storage/customer-logos konnte nicht angelegt werden.');
        }
        self::remove($customerId);
        $ext = array_search($mime, self::EXT_MIME, true);
        if (@file_put_contents(self::dir() . '/' . $customerId . '.' . $ext, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException('Das Logo konnte nicht gespeichert werden.');
        }
    }

    public static function remove(int $customerId): void
    {
        while ($f = self::file($customerId)) {
            @unlink($f);
            if (is_file($f)) {
                break;
            }
        }
    }

    public static function serve(int $customerId): never
    {
        $f = self::file($customerId);
        if (!$f) {
            http_response_code(404);
            exit;
        }
        $ext = pathinfo($f, PATHINFO_EXTENSION);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . self::EXT_MIME[$ext]);
        header('Content-Length: ' . filesize($f));
        header('Cache-Control: private, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        readfile($f);
        exit;
    }
}
