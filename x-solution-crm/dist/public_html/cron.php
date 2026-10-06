<?php
/**
 * Cronjob (alle 15 Minuten):
 *   HTTP:  https://<domain>/cron.php?secret=<CRON_SECRET>
 *   CLI:   php /pfad/zum/domain-ordner/public_html/cron.php
 *
 * 1. nicht verarbeitete Webhook-Ereignisse nachholen
 * 1b. easybill-Kontakte abgleichen (höchstens stündlich)
 * 2. geänderte Dokumente bei easybill abfragen (Sicherheitsnetz)
 * 3. fehlende PDFs laden (begrenzt pro Lauf)
 * 4. abgelaufene Verträge auf "beendet" setzen
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
if (!is_file(BASE_PATH . '/app/bootstrap.php')) {
    // Häufigster Upload-Fehler: app/, config/, storage/ liegen nicht NEBEN public_html
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("X-Solution CRM: Ordner app/ nicht gefunden.\n\n"
        . "Erwartet: " . BASE_PATH . "/app/bootstrap.php\n"
        . "Diese Datei liegt in: " . __DIR__ . "\n\n"
        . "Bitte app/, config/, storage/ und .env.example in den Ordner " . BASE_PATH . " hochladen,\n"
        . "also eine Ebene ÜBER public_html (nicht hinein).\n");
}
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Settings;
use App\Services\ContractService;
use App\Services\EasybillClient;
use App\Services\EasybillRateLimitException;
use App\Services\EasybillSync;

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    $expected = (string) env('CRON_SECRET', '');
    $given = (string) ($_GET['secret'] ?? '');
    if ($expected === '' || strlen($expected) < 16 || !hash_equals($expected, $given)) {
        http_response_code(403);
        exit("403 Forbidden\n");
    }
}

if (!App\Services\Installer::isLocked()) {
    http_response_code(503);
    exit("Noch nicht installiert.\n");
}

// Parallele Läufe verhindern
$lock = fopen(STORAGE_PATH . '/cache/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Cron läuft bereits.\n");
}
set_time_limit(240);
ignore_user_abort(true);

$start = microtime(true);
$out = [];
$status = 'ok';
$sync = new EasybillSync(EasybillClient::fromEnv());

try {
    $w = $sync->processPendingWebhooks();
    $out[] = sprintf('Webhooks nachverarbeitet: %d ok, %d Fehler', $w['ok'], $w['failed']);

    $k = $sync->syncCustomers();
    if (empty($k['skipped'])) {
        $out[] = sprintf('Kontakte: %d geprüft, %d neu, %d aktualisiert', $k['geprueft'], $k['neu'], $k['aktualisiert']);
    }

    $s = $sync->syncChanged();
    $out[] = isset($s['skipped'])
        ? 'easybill-Abgleich übersprungen: ' . $s['skipped']
        : sprintf('easybill-Abgleich: %d geprüft, %d neu, %d aktualisiert, %d unverändert', $s['geprueft'], $s['neu'], $s['aktualisiert'], $s['unveraendert']);

    if (EasybillClient::fromEnv()) {
        $out[] = 'PDFs geladen: ' . $sync->downloadMissingPdfs((int) config('easybill.pdfs_per_run', 8));
    }
} catch (EasybillRateLimitException $e) {
    $status = 'fehler';
    $out[] = $e->getMessage();
} catch (\Throwable $e) {
    $status = 'fehler';
    $out[] = 'Fehler: ' . $e->getMessage();
    log_message('cron', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $out[] = 'Verträge beendet: ' . ContractService::closeExpired();
    // Alte Protokolleinträge aufräumen (> 90 Tage)
    Database::run("DELETE FROM sync_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
} catch (\Throwable $e) {
    $status = 'fehler';
    $out[] = 'Fehler: ' . $e->getMessage();
}

$ms = (int) round((microtime(true) - $start) * 1000);
Settings::set('cron_last_run', date('Y-m-d H:i:s'));
Settings::set('cron_last_status', $status);
Database::insert('sync_log', [
    'channel' => 'cron', 'method' => $isCli ? 'CLI' : 'GET', 'endpoint' => 'cron.php',
    'status' => $status, 'message' => mb_substr(implode(' · ', $out), 0, 500), 'duration_ms' => $ms,
    'processed_at' => date('Y-m-d H:i:s'),
]);

flock($lock, LOCK_UN);
echo 'X-Solution CRM Cron ' . date('d.m.Y H:i:s') . " ({$ms} ms)\n" . implode("\n", $out) . "\n";
