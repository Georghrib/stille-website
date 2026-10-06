<?php
/**
 * Mock der easybill REST API v1 für lokale Tests (ohne echten API-Key).
 * Start:  php -S 127.0.0.1:8090 tests/mock-easybill/router.php
 * In der .env:  EASYBILL_API_KEY=test-key  und  EASYBILL_BASE_URL=http://127.0.0.1:8090/rest/v1
 *
 * Unterstützt: GET /documents (type, document_date, paid_at, page, limit), GET /documents/{id},
 * GET /documents/{id}/pdf, GET /customers, GET /customers/{id}. Antwortformat wie easybill
 * (page, pages, limit, total, items). Header "X-Mock-Ratelimit: 1" simuliert HTTP 429.
 */
declare(strict_types=1);

$data = json_decode((string) file_get_contents(__DIR__ . '/data.json'), true);
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = preg_replace('#^/rest/v1#', '', $path);
header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth !== 'Bearer test-key') {
    http_response_code(401);
    echo json_encode(['code' => 401, 'message' => 'Unauthorized']);
    return true;
}
if (is_file(sys_get_temp_dir() . '/easybill-mock-429')) {
    http_response_code(429);
    echo json_encode(['message' => 'Too Many Requests']);
    return true;
}

$inRange = static function (?string $value, string $range): bool {
    if ($range === 'null') {
        return $value === null;
    }
    if ($value === null) {
        return false;
    }
    $v = substr($value, 0, 10);
    $parts = explode(',', $range);
    return count($parts) === 2 ? ($v >= $parts[0] && $v <= $parts[1]) : $v === $parts[0];
};

if ($path === '/documents') {
    $docs = $data['documents'];
    if (!empty($_GET['type'])) {
        $types = explode(',', strtoupper($_GET['type']));
        $docs = array_filter($docs, fn ($d) => in_array($d['type'], $types, true));
    }
    foreach (['document_date', 'paid_at'] as $f) {
        if (isset($_GET[$f])) {
            $docs = array_filter($docs, fn ($d) => $inRange($d[$f] ?? null, (string) $_GET[$f]));
        }
    }
    $docs = array_values($docs);
    $limit = max(1, min(1000, (int) ($_GET['limit'] ?? 100)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    echo json_encode([
        'page' => $page, 'pages' => max(1, (int) ceil(count($docs) / $limit)), 'limit' => $limit, 'total' => count($docs),
        'items' => array_slice($docs, ($page - 1) * $limit, $limit),
    ]);
    return true;
}
if (preg_match('#^/documents/(\d+)(/pdf)?$#', $path, $m)) {
    foreach ($data['documents'] as $d) {
        if ((int) $d['id'] === (int) $m[1]) {
            if (!empty($m[2])) {
                header('Content-Type: application/pdf');
                $text = 'easybill Mock ' . $d['type'] . ' ' . $d['number'];
                $stream = "BT /F1 18 Tf 60 760 Td ($text) Tj ET";
                $objs = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
                    "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
                    "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream", "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"];
                $pdf = "%PDF-1.4\n";
                $offsets = [];
                foreach ($objs as $i => $o) {
                    $offsets[] = strlen($pdf);
                    $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
                }
                $xref = strlen($pdf);
                $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
                foreach ($offsets as $o) {
                    $pdf .= sprintf("%010d 00000 n \n", $o);
                }
                $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
                echo $pdf;
                return true;
            }
            echo json_encode($d);
            return true;
        }
    }
    http_response_code(404);
    echo json_encode(['code' => 404, 'message' => 'Document not found']);
    return true;
}
if ($path === '/customers') {
    $items = array_values($data['customers']);
    echo json_encode(['page' => 1, 'pages' => 1, 'limit' => (int) ($_GET['limit'] ?? 100), 'total' => count($items), 'items' => array_slice($items, 0, (int) ($_GET['limit'] ?? 100))]);
    return true;
}
if (preg_match('#^/customers/(\d+)$#', $path, $m) && isset($data['customers'][$m[1]])) {
    echo json_encode($data['customers'][$m[1]]);
    return true;
}
http_response_code(404);
echo json_encode(['code' => 404, 'message' => 'Not found']);
return true;
