<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;

function env(string $key, ?string $default = null): ?string
{
    return Env::get($key, $default);
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require CONFIG_PATH . '/app.php';
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** HTML-Escaping für jede Ausgabe in Templates. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $u = Request::basePath() . '/' . ltrim($path, '/');
    if ($u === '') {
        $u = '/';
    }
    return $query ? $u . '?' . http_build_query($query) : $u;
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    $publicHtml = BASE_PATH . '/public_html/assets/' . $path;
    $mtime = @filemtime(is_file($publicHtml) ? $publicHtml : $file) ?: 1;
    return url('assets/' . $path) . '?v=' . $mtime;
}

function redirect(string $path, int $code = 302): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target, true, $code);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref, true, 302);
        exit;
    }
    redirect($fallback);
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function csrf_field(): string
{
    return Csrf::field();
}

function flash(string $type, string $message): void
{
    App\Core\Session::flash($type, $message);
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

/* ---------- Formatierung de-AT ---------- */

/** Cent-Integer → "1.234,56 €" */
function money(int|string|null $cents, bool $symbol = true): string
{
    $cents = (int) ($cents ?? 0);
    $s = number_format($cents / 100, 2, ',', '.');
    return $symbol ? $s . ' €' : $s;
}

/** Kompakte Darstellung für KPIs: "12,4 Tsd. €" ab 100.000 € */
function money_short(int|string|null $cents): string
{
    $v = ((int) $cents) / 100;
    if (abs($v) >= 1_000_000) {
        return number_format($v / 1_000_000, 2, ',', '.') . ' Mio. €';
    }
    return money($cents);
}

/** Cent-Integer → "1234,56" für Eingabefelder */
function money_input(int|string|null $cents): string
{
    if ($cents === null || $cents === '') {
        return '';
    }
    return number_format(((int) $cents) / 100, 2, ',', '.');
}

/** "1.234,56" / "1234.56" / "1 234,5 €" → Cent-Integer */
function parse_money(?string $input): ?int
{
    $s = trim((string) $input);
    if ($s === '') {
        return null;
    }
    $s = str_replace(['€', ' ', "\u{00A0}", "'"], '', $s);
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    }
    if (!is_numeric($s)) {
        return null;
    }
    return (int) round(((float) $s) * 100);
}

function number_de(float|int $value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals, ',', '.');
}

/** "2026-10-06" → "06.10.2026" */
function date_de(?string $date): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '–';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : '–';
}

function datetime_de(?string $date): string
{
    if (!$date) {
        return '–';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y, H:i', $ts) : '–';
}

/** "06.10.2026" (oder ISO) → "2026-10-06", sonst null */
function parse_date_de(?string $input): ?string
{
    $s = trim((string) $input);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/', $s, $m)) {
        $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
        return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $s : null;
    }
    return null;
}

/** "06.10.2026 14:30" → "2026-10-06 14:30:00" */
function parse_datetime_de(?string $date, ?string $time = null): ?string
{
    $d = parse_date_de($date);
    if ($d === null) {
        return null;
    }
    $t = trim((string) $time);
    if ($t === '' || !preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
        $t = '09:00';
        $m = [null, 9, 0];
    }
    return sprintf('%s %02d:%02d:00', $d, $m[1], $m[2]);
}

const MONTHS_DE = [1 => 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const MONTHS_DE_SHORT = [1 => 'Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

function month_label(string $ym, bool $short = false): string
{
    [$y, $m] = array_map('intval', explode('-', $ym));
    return ($short ? MONTHS_DE_SHORT[$m] . ' ' . substr((string) $y, 2) : MONTHS_DE[$m] . ' ' . $y);
}

/** Ganze Monate zwischen zwei Daten (gerundet, mind. 0). */
function months_between(?string $start, ?string $end): ?float
{
    if (!$start || !$end) {
        return null;
    }
    $a = new DateTimeImmutable($start);
    $b = (new DateTimeImmutable($end))->modify('+1 day');
    if ($b <= $a) {
        return 0.0;
    }
    $diff = $a->diff($b);
    return round($diff->y * 12 + $diff->m + $diff->d / 30, 1);
}

function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $today = new DateTimeImmutable('today');
    $d = new DateTimeImmutable($date);
    return (int) $today->diff($d)->format('%r%a');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i ?: '?';
}

function pct_change(float|int $current, float|int $previous): ?float
{
    if ((float) $previous == 0.0) {
        return $current == 0 ? 0.0 : null;
    }
    return round((($current - $previous) / abs($previous)) * 100, 1);
}

function is_active_path(string $prefix): bool
{
    $path = Request::path();
    return $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, $prefix . '/'));
}

/* ---------- Bezeichnungen ---------- */

function customer_status_label(string $s): string
{
    return ['interessent' => 'Interessent', 'kunde' => 'Kunde', 'inaktiv' => 'Inaktiv'][$s] ?? $s;
}

function contract_status_label(string $s): string
{
    return ['entwurf' => 'Entwurf', 'aktiv' => 'Aktiv', 'gekuendigt' => 'Gekündigt', 'beendet' => 'Beendet'][$s] ?? $s;
}

function interval_label(string $s): string
{
    return ['einmalig' => 'Einmalig', 'monatlich' => 'Monatlich', 'quartal' => 'Quartalsweise', 'jaehrlich' => 'Jährlich'][$s] ?? $s;
}

function doc_type_label(string $s): string
{
    return ['angebot' => 'Angebot', 'rechnung' => 'Rechnung', 'gutschrift' => 'Gutschrift'][$s] ?? $s;
}

function doc_status_label(string $s): string
{
    return [
        'entwurf' => 'Entwurf', 'offen' => 'Offen', 'bezahlt' => 'Bezahlt', 'ueberfaellig' => 'Überfällig',
        'storniert' => 'Storniert', 'geloescht' => 'In easybill gelöscht', 'angenommen' => 'Angenommen', 'abgelehnt' => 'Abgelehnt', 'erledigt' => 'Erledigt',
    ][$s] ?? ucfirst($s);
}

/** Badge-Farbklasse: ok | warn | danger | info | muted */
function badge_class(string $key): string
{
    return [
        'kunde' => 'ok', 'interessent' => 'info', 'inaktiv' => 'muted',
        'aktiv' => 'ok', 'laufend' => 'ok', 'entwurf' => 'muted', 'gekuendigt' => 'danger', 'beendet' => 'muted',
        'verlaengerung' => 'warn', 'auslaufend' => 'danger',
        'bezahlt' => 'ok', 'offen' => 'info', 'ueberfaellig' => 'danger', 'storniert' => 'muted',
        'angenommen' => 'ok', 'abgelehnt' => 'muted', 'erledigt' => 'ok',
        'angebot' => 'info', 'rechnung' => 'ok', 'gutschrift' => 'warn',
    ][$key] ?? 'muted';
}

function badge(string $key, ?string $label = null): string
{
    return '<span class="badge badge-' . badge_class($key) . '">' . e($label ?? $key) . '</span>';
}

/** Inline-SVG-Icons (Linien-Stil, 24er Raster). */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'check' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'euro' => '<path d="M4 10h12"/><path d="M4 14h9"/><path d="M19 6a7.7 7.7 0 0 0-5.2-2A7.9 7.9 0 0 0 6 12c0 4.4 3.5 8 7.8 8 2 0 3.8-.8 5.2-2"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'trend-up' => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
        'arrow-up' => '<path d="M12 19V5"/><path d="M5 12l7-7 7 7"/>',
        'arrow-down' => '<path d="M12 5v14"/><path d="M19 12l-7 7-7-7"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'refresh' => '<path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'note' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'menu' => '<path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/>',
        'x' => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'archive' => '<path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/>',
        'swap' => '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'logo' => '<path d="M7 6l10 12" stroke-width="2.6"/><path d="M17 6L7 18" stroke-width="2.6"/>',
        'chevron-right' => '<path d="M9 18l6-6-6-6"/>',
    ];
    $p = $paths[$name] ?? $paths['file'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function log_message(string $channel, string $message): void
{
    $dir = STORAGE_PATH . '/logs';
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($dir . '/' . $channel . '-' . date('Y-m') . '.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
    }
}

function send_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}
