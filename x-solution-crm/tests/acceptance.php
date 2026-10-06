<?php
/**
 * Abnahmetest (lokal, ohne echten easybill-Key):
 *   1. Anmeldung funktioniert
 *   2. Dashboard enthält alle Diagramme
 *   3. Test-Webhook legt ein Dokument an (und Duplikate werden aktualisiert)
 *   4. Dokument lässt sich im Posteingang als Vertrag übernehmen
 *   + Cron/Webhook-Schutz (403), PDF nur mit Login
 *
 * Aufruf:  php tests/acceptance.php http://127.0.0.1:8080 admin@example.at 'Passwort'
 * Voraussetzung: installierte App (install.php gelaufen), .env im Projektordner.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;

[$self, $base, $email, $password] = $argv + [null, 'http://127.0.0.1:8080', '', ''];
$base = rtrim((string) $base, '/');
if ($email === '' || $password === '') {
    fwrite(STDERR, "Aufruf: php tests/acceptance.php <APP_URL> <E-Mail> <Passwort>\n");
    exit(2);
}

$cookie = tempnam(sys_get_temp_dir(), 'xcrm');
$failures = 0;

function http(string $method, string $url, array|string|null $body = null, array $headers = []): array
{
    global $cookie;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    $start = microtime(true);
    $res = (string) curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [
        'status' => (int) $info['http_code'],
        'location' => (string) ($info['redirect_url'] ?? ''),
        'body' => substr($res, (int) $info['header_size']),
        'ms' => (int) round((microtime(true) - $start) * 1000),
    ];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? "  \u{2714} " : "  \u{2718} ") . $label . ($detail !== '' ? " – $detail" : '') . "\n";
    if (!$ok) {
        $failures++;
    }
}

function csrf(string $html): string
{
    return preg_match('/name="(?:_csrf|csrf-token)" (?:value|content)="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
}

echo "X-Solution CRM – Abnahmetest gegen $base\n\n";

// 1. Login
echo "1. Anmeldung\n";
$r = http('GET', "$base/");
check('Ohne Login Weiterleitung zur Anmeldung', $r['status'] === 302 && str_contains($r['location'], '/login'));
$r = http('GET', "$base/login");
$r = http('POST', "$base/login", ['_csrf' => csrf($r['body']), 'email' => $email, 'password' => $password]);
check('Login mit Zugangsdaten', $r['status'] === 302 && !str_contains($r['location'], '/login'), 'HTTP ' . $r['status']);

// 2. Dashboard
echo "2. Dashboard\n";
$r = http('GET', "$base/");
check('Dashboard lädt', $r['status'] === 200);
foreach (['chart-revenue' => 'Umsatzentwicklung', 'chart-durations' => 'Vertragslaufzeiten', 'chart-status' => 'Vertragsstatus'] as $id => $label) {
    check("Diagramm „{$label}“ inkl. Daten", str_contains($r['body'], 'id="' . $id . '"') && str_contains($r['body'], 'id="' . $id . '-data"'));
}
foreach (['Monatsumsatz', 'Jahresumsatz', 'Aktive Kunden', 'Ø Vertragslaufzeit', 'Aktive Verträge', 'Umsatzprognose', 'Offene Opportunities', 'Aus easybill', 'Erinnerungen &amp; Verlängerungen'] as $t) {
    check("Bereich „" . html_entity_decode($t) . "“", str_contains($r['body'], $t));
}
check('Chart.js per CDN eingebunden', str_contains($r['body'], 'cdn.jsdelivr.net/npm/chart.js'));
$csrf = csrf($r['body']);

// 3. Webhook
echo "3. Webhook\n";
$secret = (string) env('WEBHOOK_SECRET', '');
$ebId = random_int(100000000, 199999999);
$doc = json_decode((string) file_get_contents(__DIR__ . '/payloads/01-document-create-offer.json'), true);
$doc['data']['id'] = $ebId;
$doc['data']['number'] = 'AN-T' . substr((string) $ebId, -4);
$doc['data']['customer_id'] = $ebId; // unbekannter Kunde → Posteingang
$doc['data']['customer_snapshot']['emails'] = ['abnahme-' . $ebId . '@example.at'];
$doc['data']['customer_snapshot']['company_name'] = 'Abnahmetest GmbH';
$payload = json_encode($doc);
check('Ohne Secret → 403', http('POST', "$base/easybill-webhook.php", $payload)['status'] === 403);
check('Falsches Secret → 403', http('POST', "$base/easybill-webhook.php?secret=falschfalschfalschfalsch", $payload)['status'] === 403);
$r = http('POST', "$base/easybill-webhook.php?secret=" . urlencode($secret), $payload, ['Content-Type: application/json']);
check('Webhook antwortet mit 200', $r['status'] === 200, $r['ms'] . ' ms');
usleep(800_000);
$row = Database::one('SELECT * FROM easybill_documents WHERE easybill_id = ?', [$ebId]);
check('Dokument angelegt und im Posteingang', $row !== null && $row['inbox_state'] === 'neu', $row ? $row['number'] : 'fehlt');
http('POST', "$base/easybill-webhook.php?secret=" . urlencode($secret), $payload, ['Content-Type: application/json']);
usleep(500_000);
check('Erneuter Empfang erzeugt kein Duplikat', (int) Database::value('SELECT COUNT(*) FROM easybill_documents WHERE easybill_id = ?', [$ebId]) === 1);
$r = http('GET', "$base/easybill");
check('Beleg im Posteingang sichtbar', str_contains($r['body'], $doc['data']['number']));

// 4. Übernahme als Vertrag
echo "4. Übernahme als Vertrag\n";
$r = http('POST', "$base/easybill/{$row['id']}/uebernehmen", [
    '_csrf' => $csrf, 'mode' => 'vertrag', 'customer_choice' => 'neu',
    'customer_name' => 'Abnahmetest GmbH', 'customer_email' => 'abnahme-' . $ebId . '@example.at',
    'title' => 'Abnahme-Vertrag', 'status' => 'aktiv', 'billing_interval' => 'monatlich',
    'start_date' => date('01.m.Y'), 'end_date' => date('d.m.Y', strtotime(date('Y-m-01') . ' +24 months -1 day')),
    'total_value' => '12.000,00', 'monthly_value' => '', 'notice_days' => '90',
]);
check('Übernahme leitet zum Vertrag weiter', $r['status'] === 302 && str_contains($r['location'], '/vertraege/'), $r['location']);
$row = Database::one('SELECT * FROM easybill_documents WHERE easybill_id = ?', [$ebId]);
$contract = $row && $row['contract_id'] ? Database::one('SELECT c.*, cu.status cstatus FROM contracts c JOIN customers cu ON cu.id = c.customer_id WHERE c.id = ?', [$row['contract_id']]) : null;
check('Vertrag erzeugt und mit Dokument verknüpft', $contract !== null && (int) $contract['source_document_id'] === (int) $row['id']);
check('Monatswert berechnet (12.000 € / 24 Monate = 500 €)', $contract && (int) $contract['monthly_value_cents'] === 50000, $contract ? money($contract['monthly_value_cents']) : '');
check('Kunde ist aktiv (Status „Kunde“)', $contract && $contract['cstatus'] === 'kunde');
check('Beleg nicht mehr im Posteingang', $row['inbox_state'] === 'vertrag');

// Zusatz: Schutzmechanismen
echo "5. Schutz\n";
check('cron.php ohne Secret → 403', http('GET', "$base/cron.php")['status'] === 403);
$r = http('GET', "$base/cron.php?secret=" . urlencode((string) env('CRON_SECRET', '')));
check('cron.php mit Secret läuft', $r['status'] === 200 && str_contains($r['body'], 'Cron'), trim(explode("\n", $r['body'])[0] ?? ''));
check('install.php gesperrt', http('GET', "$base/install.php")['status'] === 403);
check('POST ohne CSRF-Token wird abgelehnt', http('POST', "$base/kunden", ['name' => 'X'])['status'] === 403);
http('POST', "$base/logout", ['_csrf' => $csrf]);
check('PDF-Auslieferung nur mit Login', http('GET', "$base/easybill/{$row['id']}/pdf")['status'] === 302);

// Aufräumen
Database::run('DELETE FROM contracts WHERE id = ?', [$contract['id'] ?? 0]);
Database::run('DELETE FROM customers WHERE id = ?', [$contract['customer_id'] ?? 0]);
Database::run('DELETE FROM easybill_documents WHERE easybill_id = ?', [$ebId]);
@unlink($cookie);

echo "\n" . ($failures === 0 ? "Alle Prüfungen bestanden.\n" : "$failures Prüfung(en) fehlgeschlagen.\n");
exit($failures === 0 ? 0 : 1);
