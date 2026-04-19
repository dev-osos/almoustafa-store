<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/response.php';
require_once dirname(__DIR__, 2) . '/db.php';

api_response_init();
api_require_method(['POST']);

// ── 1. Origin guard ──────────────────────────────────────────────────────────
// Only allow requests originating from the store's own domain.
$origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowed = ['almoustafa.site', 'www.almoustafa.site' , 'almoustafa.store', 'www.almoustafa.store'];
$originOk = false;
foreach ($allowed as $a) {
    if (str_contains($origin, $a) || str_contains($referer, $a)) {
        $originOk = true;
        break;
    }
}
// Also allow same-host requests (no Origin header = same-origin in some browsers)
if (!$originOk && $origin === '' && $referer === '') {
    $originOk = false; // explicit curl/script calls with no headers → block
}
if (!$originOk) {
    api_error('forbidden', 403);
}

// ── 2. Valid visitor session (v_id cookie) ───────────────────────────────────
// Proves the caller went through the normal store flow, not a raw script.
$cookieName = api_env('V_ID_COOKIE_NAME', 'v_id') ?? 'v_id';
$vId        = $_COOKIE[$cookieName] ?? '';
if (!preg_match('/^v_[0-9a-f]{32}$/', $vId)) {
    api_error('unauthorized', 401);
}
try {
    $pdo  = api_pdo();
    $stmt = $pdo->prepare('SELECT id FROM visitors WHERE v_id = ? LIMIT 1');
    $stmt->execute([$vId]);
    if (!$stmt->fetch()) {
        api_error('unauthorized', 401);
    }
} catch (Throwable) {
    // DB unavailable — degrade gracefully rather than blocking real users
}

// ── 3. IP-based rate limiting (sliding window, file-backed) ──────────────────
// Max 10 requests per IP per 60 seconds.
$ip      = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown')[0]);
$rateDir = sys_get_temp_dir();
$rateKey = $rateDir . '/alm_ship_' . md5($ip) . '.rl';
$limit   = 30;
$window  = 60;
$now     = time();

$fp = @fopen($rateKey, 'c+');
if ($fp !== false) {
    if (flock($fp, LOCK_EX)) {
        $raw   = stream_get_contents($fp);
        $times = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($times)) {
            $times = [];
        }
        // Discard timestamps outside the window
        $times = array_values(array_filter($times, fn(int|float $t): bool => ($now - $t) < $window));
        if (count($times) >= $limit) {
            flock($fp, LOCK_UN);
            fclose($fp);
            http_response_code(429);
            header('Retry-After: ' . $window);
            echo json_encode(['ok' => false, 'error' => 'rate_limited'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $times[] = $now;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($times));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// ── 4. Strict input validation ───────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    api_error('invalid_json', 400);
}

$govId  = filter_var($body['govId']  ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99999]]);
$cityId = filter_var($body['cityId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99999]]);
$price  = filter_var($body['price']  ?? null, FILTER_VALIDATE_FLOAT);
$weight = filter_var($body['weight'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);

if ($govId === false || $govId === null ||
    $cityId === false || $cityId === null ||
    $price === false  || $price === null  ||
    $weight === false || $weight === null) {
    api_error('invalid_params', 400);
}
if ($price < 0 || $price > 999999) {
    api_error('invalid_params', 400);
}

// ── 5. Upstream request to TG ────────────────────────────────────────────────
$payload = json_encode([
    'operationName' => 'CalculateShipmentFees',
    'variables'     => [
        'input' => [
            'price'              => (float) $price,
            'recipientSubzoneId' => (int) $cityId,
            'recipientZoneId'    => (int) $govId,
            'serviceId'          => 1,
            'weight'             => (int) $weight,
            'paymentTypeCode'    => 'COLC',
            'priceTypeCode'      => 'INCLD',
            'senderSubzoneId'    => 346,
            'senderZoneId'       => 1,
            'size'               => ['height' => 0, 'length' => 0, 'width' => 0],
        ],
    ],
    'query' => "query CalculateShipmentFees(\$input: CalculateShipmentFeesInput!) {\n  calculateShipmentFees(input: \$input) {\n    amount\n    delivery\n    weight\n    collection\n    total\n    tax\n    post\n    return\n  }\n}",
]);

$ch = curl_init('https://system.telegraphex.com:8443/graphql');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Accept: */*',
        'Authorization: Bearer 245467|m90rxf6dkwYyeku570WIGKSuyhkZr1Kt2ehSUQVLf862e568',
        'Content-Type: application/json',
        'x-app-version: 5.2.2',
        'x-client-name: Mac OS-Safari',
        'x-client-type: WEB',
    ],
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
curl_close($ch);

if (!$resp) {
    api_error('upstream_error', 502);
}

$data = json_decode($resp, true);
$fees = $data['data']['calculateShipmentFees'] ?? null;
if (!is_array($fees)) {
    api_error('invalid_response', 502);
}

$delivery = (float) ($fees['delivery'] ?? 0)
          + (float) ($fees['weight']   ?? 0)
          + (float) ($fees['collection'] ?? 0);

api_ok(['delivery' => $delivery]);
