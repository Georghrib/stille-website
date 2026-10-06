<?php
/**
 * easybill-Webhook-Empfänger.
 * URL: https://<domain>/easybill-webhook.php?secret=<WEBHOOK_SECRET>
 * Prüft das Secret, speichert das Ereignis, antwortet sofort mit HTTP 200 und verarbeitet danach.
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
use App\Services\EasybillClient;
use App\Services\EasybillSync;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$expected = (string) env('WEBHOOK_SECRET', '');
$given = (string) ($_GET['secret'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_EASYBILL_SECRET'] ?? '');
if ($expected === '' || strlen($expected) < 16 || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET' || $method === 'HEAD') {
    // Erreichbarkeitstest (z. B. beim Anlegen des Webhooks in easybill)
    echo json_encode(['ok' => true, 'service' => 'X-Solution CRM easybill-Webhook']);
    exit;
}
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

$body = (string) file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024);
$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid json']);
    exit;
}

try {
    $event = (string) ($payload['event'] ?? $payload['action'] ?? 'unbekannt');
    $docId = $payload['data']['id'] ?? $payload['document']['id'] ?? $payload['id'] ?? null;
    $logId = Database::insert('sync_log', [
        'channel' => 'webhook',
        'method' => 'POST',
        'endpoint' => 'easybill-webhook.php',
        'http_status' => 200,
        'status' => 'empfangen',
        'message' => mb_substr($event . ($docId ? ' · Dokument ' . $docId : ''), 0, 500),
        'payload' => $body,
    ]);
} catch (\Throwable $e) {
    log_message('webhook', 'Speichern fehlgeschlagen: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'storage failed']);
    exit;
}

// Sofort mit 200 antworten, Verbindung schließen, dann verarbeiten.
ignore_user_abort(true);
set_time_limit(120);
$response = json_encode(['ok' => true, 'id' => $logId]);
http_response_code(200);
header('Connection: close');
header('Content-Length: ' . strlen($response));
echo $response;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} else {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

try {
    (new EasybillSync(EasybillClient::fromEnv()))->processWebhookLog($logId);
} catch (\Throwable $e) {
    // bleibt mit Status "fehler" in sync_log; cron.php versucht es erneut
}
