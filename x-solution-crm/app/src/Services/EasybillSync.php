<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * Import von easybill-Dokumenten (Webhook + Cron-Abgleich).
 *
 * - easybill-ID ist eindeutig: erneuter Empfang aktualisiert den Datensatz.
 * - Kundenzuordnung über easybill-Kunden-ID, ersatzweise E-Mail; sonst Posteingang.
 * - Wird ein importiertes Angebot zur Rechnung (ref_id), entsteht ein Umstellungsvorschlag.
 */
final class EasybillSync
{
    /** @var array<int,array> */
    private array $customerCache = [];

    public function __construct(private ?EasybillClient $client = null)
    {
    }

    /* ======================= Import eines Dokuments ======================= */

    /**
     * @return array{id:int, action:string}|null  null = Dokumenttyp wird nicht importiert
     */
    public function importDocument(array $doc): ?array
    {
        $types = (array) config('easybill.types');
        $ebType = strtoupper((string) ($doc['type'] ?? ''));
        if (!isset($types[$ebType]) || empty($doc['id'])) {
            return null;
        }
        $type = $types[$ebType];
        $ebId = (int) $doc['id'];
        $ebCustomerId = !empty($doc['customer_id']) ? (int) $doc['customer_id'] : null;
        [$name, $email] = $this->customerIdentity($doc, $ebCustomerId);

        $fields = [
            'easybill_customer_id' => $ebCustomerId,
            'type' => $type,
            'number' => self::str($doc['number'] ?? null, 60),
            'title' => self::str($doc['title'] ?? null, 255) ?? self::firstItemText($doc),
            'customer_name' => self::str($name, 190),
            'customer_email' => self::str($email ? mb_strtolower($email) : null, 190),
            'amount_net_cents' => abs((int) ($doc['amount_net'] ?? 0)),
            'amount_gross_cents' => abs((int) ($doc['amount'] ?? 0)),
            'currency' => strtoupper(substr((string) ($doc['currency'] ?? 'EUR'), 0, 3)) ?: 'EUR',
            'document_date' => self::date($doc['document_date'] ?? null),
            'due_date' => self::date($doc['due_date'] ?? null),
            'paid_at' => self::date($doc['paid_at'] ?? null),
            'status' => self::mapStatus($type, $doc),
            'is_draft' => !empty($doc['is_draft']) ? 1 : 0,
            'ref_easybill_id' => !empty($doc['ref_id']) ? (int) $doc['ref_id'] : null,
            'raw_payload' => json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'easybill_edited_at' => self::datetime($doc['edited_at'] ?? null),
        ];

        return Database::transaction(function () use ($ebId, $fields, $ebCustomerId, $email, $type) {
            $existing = Database::one('SELECT * FROM easybill_documents WHERE easybill_id = ? FOR UPDATE', [$ebId]);
            if ($existing) {
                $update = $fields;
                // Zuordnung nachholen, falls inzwischen ein passender Kunde existiert
                if (!$existing['customer_id'] && ($cid = $this->matchCustomer($ebCustomerId, $email))) {
                    $update['customer_id'] = $cid;
                    if ($existing['inbox_state'] === 'neu') {
                        $update['inbox_state'] = 'zugeordnet';
                    }
                }
                Database::update('easybill_documents', (int) $existing['id'], $update);
                return ['id' => (int) $existing['id'], 'action' => 'aktualisiert'];
            }

            $customerId = $this->matchCustomer($ebCustomerId, $email);
            $row = $fields + [
                'easybill_id' => $ebId,
                'customer_id' => $customerId,
                'inbox_state' => $customerId ? 'zugeordnet' : 'neu',
            ];

            // Angebot → Rechnung?
            if ($type === 'rechnung' && $fields['ref_easybill_id']) {
                $offer = Database::one("SELECT d.*, c.status AS contract_status FROM easybill_documents d LEFT JOIN contracts c ON c.id = d.contract_id WHERE d.easybill_id = ? AND d.type = 'angebot'", [$fields['ref_easybill_id']]);
                if ($offer) {
                    $row['customer_id'] ??= $offer['customer_id'];
                    if ($row['customer_id']) {
                        $row['inbox_state'] = 'zugeordnet';
                    }
                    if ($offer['contract_id'] && $offer['contract_status'] === 'aktiv') {
                        // Vertrag läuft bereits: Rechnung einfach anhängen
                        $row['contract_id'] = $offer['contract_id'];
                    } else {
                        $row['suggestion'] = 'umstellung';
                        $row['suggestion_ref_id'] = $offer['id'];
                    }
                }
            }
            $id = Database::insert('easybill_documents', $row);
            if ($customerId && $ebCustomerId) {
                Database::run('UPDATE customers SET easybill_customer_id = ? WHERE id = ? AND easybill_customer_id IS NULL AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM customers WHERE easybill_customer_id = ?) x)', [$ebCustomerId, $customerId, $ebCustomerId]);
            }
            return ['id' => $id, 'action' => 'neu'];
        });
    }

    /** In easybill gelöscht: unbearbeitete Posteingangs-Belege entfernen, andere markieren. */
    public function markDeleted(int $ebId): ?string
    {
        $row = Database::one('SELECT * FROM easybill_documents WHERE easybill_id = ?', [$ebId]);
        if (!$row) {
            return null;
        }
        if ($row['inbox_state'] === 'neu' && !$row['contract_id']) {
            Database::run('DELETE FROM easybill_documents WHERE id = ?', [$row['id']]);
            $this->deletePdf($row);
            return 'entfernt';
        }
        Database::update('easybill_documents', (int) $row['id'], ['status' => 'geloescht', 'suggestion' => null]);
        return 'als gelöscht markiert';
    }

    public function matchCustomer(?int $ebCustomerId, ?string $email): ?int
    {
        if ($ebCustomerId) {
            $id = Database::value('SELECT id FROM customers WHERE easybill_customer_id = ?', [$ebCustomerId]);
            if ($id) {
                return (int) $id;
            }
        }
        if ($email) {
            $id = Database::value('SELECT id FROM customers WHERE LOWER(email) = ? ORDER BY id LIMIT 1', [mb_strtolower(trim($email))]);
            if ($id) {
                return (int) $id;
            }
        }
        return null;
    }

    /** Kundenname/E-Mail aus customer_snapshot bzw. Dokument; notfalls per API nachladen. */
    private function customerIdentity(array $doc, ?int $ebCustomerId): array
    {
        $snap = is_array($doc['customer_snapshot'] ?? null) ? $doc['customer_snapshot'] : [];
        if (!$snap && $ebCustomerId && $this->client) {
            try {
                $snap = $this->customerCache[$ebCustomerId] ??= $this->client->customer($ebCustomerId);
            } catch (\Throwable) {
                $snap = [];
            }
        }
        $name = self::customerName($snap);
        if ($name === null && is_array($doc['address'] ?? null)) {
            $name = self::customerName($doc['address']);
        }
        $emails = $snap['emails'] ?? [];
        $email = is_array($emails) ? ($emails[0] ?? null) : (is_string($emails) ? $emails : null);
        $email = $email ?: ($snap['email'] ?? null) ?: ($doc['email'] ?? null);
        return [$name, is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null];
    }

    public static function customerName(array $c): ?string
    {
        $company = trim((string) ($c['company_name'] ?? ''));
        $person = trim(trim((string) ($c['first_name'] ?? '')) . ' ' . trim((string) ($c['last_name'] ?? '')));
        return $company !== '' ? $company : ($person !== '' ? $person : null);
    }

    public static function mapStatus(string $type, array $doc): string
    {
        if (!empty($doc['is_draft'])) {
            return 'entwurf';
        }
        $ebStatus = strtoupper((string) ($doc['status'] ?? ''));
        return match ($type) {
            'rechnung' => !empty($doc['cancel_id']) ? 'storniert'
                : (!empty($doc['paid_at']) ? 'bezahlt'
                : (!empty($doc['due_date']) && substr((string) $doc['due_date'], 0, 10) < date('Y-m-d') ? 'ueberfaellig' : 'offen')),
            'gutschrift' => !empty($doc['paid_at']) || $ebStatus === 'DONE' ? 'erledigt' : 'offen',
            default => match ($ebStatus) {
                'ACCEPT' => 'angenommen',
                'DROPPED' => 'abgelehnt',
                'DONE' => 'erledigt',
                default => 'offen',
            },
        };
    }

    /* ======================= PDFs ======================= */

    /** Lädt das PDF einmalig nach storage/pdfs. */
    public function fetchPdf(array $row): bool
    {
        if (!$this->client || !empty($row['pdf_path']) || !empty($row['is_draft'])) {
            return false;
        }
        $bytes = $this->client->documentPdf((int) $row['easybill_id']);
        if (!str_starts_with($bytes, '%PDF')) {
            throw new EasybillException('easybill hat kein gültiges PDF geliefert.');
        }
        $year = $row['document_date'] ? substr((string) $row['document_date'], 0, 4) : date('Y');
        $rel = 'pdfs/' . $year . '/' . (int) $row['easybill_id'] . '.pdf';
        $abs = STORAGE_PATH . '/' . $rel;
        if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0750, true)) {
            throw new \RuntimeException('Ordner storage/pdfs ist nicht beschreibbar.');
        }
        if (@file_put_contents($abs, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException('PDF konnte nicht gespeichert werden.');
        }
        Database::run('UPDATE easybill_documents SET pdf_path = ? WHERE id = ?', [$rel, $row['id']]);
        return true;
    }

    public function downloadMissingPdfs(int $limit): int
    {
        $rows = Database::all("SELECT * FROM easybill_documents WHERE pdf_path IS NULL AND is_draft = 0 AND status <> 'geloescht' AND raw_payload NOT LIKE '%\"demo\":true%' ORDER BY imported_at DESC LIMIT " . max(0, $limit));
        $n = 0;
        foreach ($rows as $r) {
            try {
                $n += $this->fetchPdf($r) ? 1 : 0;
            } catch (EasybillRateLimitException $e) {
                throw $e;
            } catch (\Throwable $e) {
                log_message('easybill', 'PDF ' . $r['easybill_id'] . ': ' . $e->getMessage());
            }
        }
        return $n;
    }

    private function deletePdf(array $row): void
    {
        if (!empty($row['pdf_path'])) {
            $abs = realpath(STORAGE_PATH . '/' . $row['pdf_path']);
            if ($abs && str_starts_with($abs, realpath(STORAGE_PATH . '/pdfs') ?: "\0")) {
                @unlink($abs);
            }
        }
    }

    /* ======================= Webhook ======================= */

    /** Verarbeitet ein gespeichertes Webhook-Ereignis aus sync_log. */
    public function processWebhookLog(int $logId): string
    {
        $log = Database::one("SELECT * FROM sync_log WHERE id = ? AND channel = 'webhook'", [$logId]);
        if (!$log || $log['status'] === 'verarbeitet') {
            return 'bereits verarbeitet';
        }
        Database::run('UPDATE sync_log SET attempts = attempts + 1 WHERE id = ?', [$logId]);
        try {
            $result = $this->handleWebhookPayload(json_decode((string) $log['payload'], true) ?: []);
            Database::run("UPDATE sync_log SET status = ?, message = ?, processed_at = NOW() WHERE id = ?", [
                $result['ignored'] ? 'ignoriert' : 'verarbeitet', mb_substr($result['message'], 0, 500), $logId,
            ]);
            return $result['message'];
        } catch (\Throwable $e) {
            Database::run("UPDATE sync_log SET status = 'fehler', message = ?, processed_at = NOW() WHERE id = ?", [mb_substr('Fehler: ' . $e->getMessage(), 0, 500), $logId]);
            log_message('easybill', 'Webhook #' . $logId . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /** @return array{message:string, ignored:bool} */
    public function handleWebhookPayload(array $payload): array
    {
        $event = strtolower((string) ($payload['event'] ?? $payload['type_event'] ?? $payload['action'] ?? ''));
        $doc = $payload['data']['document'] ?? $payload['data'] ?? $payload['document'] ?? null;
        if (!is_array($doc) && isset($payload['id'], $payload['type'])) {
            $doc = $payload; // Dokument direkt als Payload
        }
        // Zahlungs-Ereignisse liefern die Zahlung (mit document_id), nicht das Dokument
        if (str_contains($event, 'payment') && is_array($doc)) {
            $docId = (int) ($doc['document_id'] ?? 0);
            if ($docId <= 0 || !$this->client) {
                return ['message' => 'Zahlungs-Ereignis ohne Dokument-ID bzw. ohne API-Key – Abgleich per Cron', 'ignored' => true];
            }
            $doc = ['id' => $docId];
        }
        if (!is_array($doc) || empty($doc['id'])) {
            return ['message' => 'Kein Dokument im Ereignis' . ($event ? ' (' . $event . ')' : ''), 'ignored' => true];
        }
        $ebId = (int) $doc['id'];
        if ($event !== '' && (str_contains($event, 'delete') || str_contains($event, 'remove'))) {
            $r = $this->markDeleted($ebId);
            return ['message' => 'Dokument ' . $ebId . ' in easybill gelöscht: ' . ($r ?? 'nicht vorhanden'), 'ignored' => $r === null];
        }
        // Nur ID/Teil-Daten geliefert → vollständiges Dokument laden
        if ((!isset($doc['type']) || !array_key_exists('amount', $doc)) && $this->client) {
            $doc = $this->client->document($ebId);
        }
        $res = $this->importDocument($doc);
        if ($res === null) {
            return ['message' => 'Dokumenttyp ' . ($doc['type'] ?? '?') . ' wird nicht importiert', 'ignored' => true];
        }
        $msg = 'Dokument ' . ($doc['number'] ?? $ebId) . ' ' . $res['action'];
        $row = Database::one('SELECT * FROM easybill_documents WHERE id = ?', [$res['id']]);
        if ($row && $this->client) {
            try {
                if ($this->fetchPdf($row)) {
                    $msg .= ', PDF gespeichert';
                }
            } catch (\Throwable $e) {
                $msg .= ', PDF folgt per Cron (' . $e->getMessage() . ')';
            }
        }
        return ['message' => $msg, 'ignored' => false];
    }

    public function processPendingWebhooks(int $limit = 50): array
    {
        $ids = Database::all("SELECT id FROM sync_log WHERE channel = 'webhook' AND status IN ('empfangen','fehler') AND attempts < 5 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY id LIMIT " . $limit);
        $ok = 0;
        $failed = 0;
        foreach ($ids as $r) {
            try {
                $this->processWebhookLog((int) $r['id']);
                $ok++;
            } catch (EasybillRateLimitException $e) {
                throw $e;
            } catch (\Throwable) {
                $failed++;
            }
        }
        return ['ok' => $ok, 'failed' => $failed];
    }

    /* ======================= Cron-Abgleich ======================= */

    /**
     * Holt alle seit dem letzten Lauf geänderten Dokumente (Sicherheitsnetz für verpasste Webhooks).
     * easybill bietet keinen "geändert seit"-Filter für /documents; daher werden
     * Belegdatum- und Bezahlt-Zeitraum abgefragt und per edited_at gefiltert.
     */
    public function syncChanged(): array
    {
        if (!$this->client) {
            return ['skipped' => 'Kein EASYBILL_API_KEY gesetzt'];
        }
        $runStartedAt = date('Y-m-d H:i:s');
        $last = Settings::get('easybill_last_sync');
        $lookback = (int) config('easybill.lookback_days', 45);
        $fromDate = $last ? date('Y-m-d', strtotime($last . ' -' . $lookback . ' days')) : date('Y-m-d', strtotime('-365 days'));
        $paidFrom = $last ? date('Y-m-d', strtotime($last . ' -2 days')) : $fromDate;
        $today = date('Y-m-d', strtotime('+1 day'));
        $type = implode(',', array_keys((array) config('easybill.types')));

        $docs = [];
        foreach ($this->client->allDocuments(['type' => $type, 'document_date' => $fromDate . ',' . $today]) as $d) {
            $docs[(int) $d['id']] = $d;
        }
        foreach ($this->client->allDocuments(['type' => $type, 'paid_at' => $paidFrom . ',' . $today]) as $d) {
            $docs[(int) $d['id']] = $d;
        }

        $stats = ['geprueft' => count($docs), 'neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0];
        $known = $docs ? array_column(Database::all(
            'SELECT easybill_id, easybill_edited_at FROM easybill_documents WHERE easybill_id IN (' . implode(',', array_map('intval', array_keys($docs))) . ')'
        ), 'easybill_edited_at', 'easybill_id') : [];
        foreach ($docs as $id => $d) {
            $edited = self::datetime($d['edited_at'] ?? null);
            if (array_key_exists($id, $known) && $edited !== null && $known[$id] !== null && $edited <= $known[$id]) {
                $stats['unveraendert']++;
                continue;
            }
            $res = $this->importDocument($d);
            if ($res) {
                $stats[$res['action']]++;
            }
        }
        Settings::set('easybill_last_sync', $runStartedAt);
        return $stats;
    }

    /* ======================= Hilfen ======================= */

    private static function str(mixed $v, int $max): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private static function date(mixed $v): ?string
    {
        if (!is_string($v) || $v === '') {
            return null;
        }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private static function datetime(mixed $v): ?string
    {
        if (!is_string($v) || $v === '') {
            return null;
        }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private static function firstItemText(array $doc): ?string
    {
        $item = $doc['items'][0]['description'] ?? null;
        return is_string($item) ? mb_substr(trim(strip_tags($item)), 0, 255) : null;
    }
}
