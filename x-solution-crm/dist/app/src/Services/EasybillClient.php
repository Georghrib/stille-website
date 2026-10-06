<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * REST-Client für https://api.easybill.de/rest/v1 (Bearer-Token, nur curl).
 * Jeder Aufruf wird in sync_log protokolliert (ohne API-Key).
 */
final class EasybillClient
{
    private string $baseUrl;
    /** Anfragen in diesem PHP-Prozess (Schutz vor dem Ratelimit, siehe config easybill.max_requests_per_run). */
    private static int $requestCount = 0;

    public function __construct(private string $apiKey, ?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) (env('EASYBILL_BASE_URL') ?? config('easybill.base_url')), '/');
    }

    public static function fromEnv(): ?self
    {
        $key = env('EASYBILL_API_KEY');
        return $key ? new self($key) : null;
    }

    /** @return array{items: array<int,array>, page:int, pages:int, total:int} */
    public function documents(array $query = []): array
    {
        $res = $this->request('GET', '/documents', $query);
        return [
            'items' => $res['items'] ?? [],
            'page' => (int) ($res['page'] ?? 1),
            'pages' => (int) ($res['pages'] ?? 1),
            'total' => (int) ($res['total'] ?? count($res['items'] ?? [])),
        ];
    }

    /** Alle Seiten einer Dokumentabfrage. */
    public function allDocuments(array $query, int $maxPages = 20): array
    {
        $items = [];
        $page = 1;
        do {
            $res = $this->documents($query + ['page' => $page, 'limit' => 1000]);
            array_push($items, ...$res['items']);
            $page++;
        } while ($page <= $res['pages'] && $page <= $maxPages);
        return $items;
    }

    public function document(int $id): array
    {
        return $this->request('GET', '/documents/' . $id);
    }

    public function documentPdf(int $id): string
    {
        return $this->request('GET', '/documents/' . $id . '/pdf', [], true);
    }

    public function customer(int $id): array
    {
        return $this->request('GET', '/customers/' . $id);
    }

    /** Verbindungstest (leichtgewichtiger Aufruf). */
    public function ping(): array
    {
        return $this->request('GET', '/customers', ['limit' => 1]);
    }

    public function request(string $method, string $path, array $query = [], bool $raw = false): mixed
    {
        if (++self::$requestCount > (int) config('easybill.max_requests_per_run', 9)) {
            throw new EasybillRateLimitException('Anfragelimit pro Lauf erreicht (easybill-Ratelimit) – der nächste Cron-Lauf macht weiter.');
        }
        $url = $this->baseUrl . $path . ($query ? '?' . http_build_query($query) : '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) config('easybill.timeout', 20),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: ' . ($raw ? 'application/pdf' : 'application/json'),
                'User-Agent: X-Solution-CRM/1.0',
            ],
        ]);
        $start = microtime(true);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $endpoint = $path . ($query ? '?' . http_build_query($query) : '');
        if ($body === false) {
            self::log($method, $endpoint, null, 'fehler', 'Verbindungsfehler: ' . $error, $ms);
            throw new EasybillException('Keine Verbindung zu easybill: ' . $error);
        }
        if ($status === 429) {
            self::log($method, $endpoint, $status, 'fehler', 'Ratelimit erreicht', $ms);
            throw new EasybillRateLimitException('easybill-Ratelimit erreicht – der nächste Cron-Lauf macht weiter.');
        }
        if ($status < 200 || $status >= 300) {
            $msg = is_string($body) ? mb_substr(trim(strip_tags($body)), 0, 300) : '';
            self::log($method, $endpoint, $status, 'fehler', 'HTTP ' . $status . ($msg !== '' ? ': ' . $msg : ''), $ms);
            throw new EasybillException(match ($status) {
                401, 403 => 'easybill hat den API-Schlüssel abgelehnt (HTTP ' . $status . ').',
                404 => 'Dokument bei easybill nicht gefunden (HTTP 404).',
                default => 'easybill-Fehler HTTP ' . $status . '.',
            }, $status);
        }
        self::log($method, $endpoint, $status, 'ok', $raw ? 'PDF ' . strlen((string) $body) . ' Bytes' : null, $ms);
        if ($raw) {
            return (string) $body;
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new EasybillException('Ungültige JSON-Antwort von easybill.');
        }
        return $data;
    }

    private static function log(string $method, string $endpoint, ?int $status, string $state, ?string $message, int $ms): void
    {
        try {
            Database::insert('sync_log', [
                'channel' => 'api', 'method' => $method, 'endpoint' => mb_substr($endpoint, 0, 255),
                'http_status' => $status, 'status' => $state, 'message' => $message ? mb_substr($message, 0, 500) : null,
                'duration_ms' => $ms, 'processed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Protokollierung darf den Abgleich nie abbrechen
        }
    }
}
