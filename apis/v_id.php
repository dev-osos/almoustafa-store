<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

api_response_init();
api_require_method(['GET', 'POST']);

function getClientIp(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = trim((string)$_SERVER[$key]);
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }
            return $value;
        }
    }

    return '';
}

function isValidVid(string $value): bool
{
    return (bool) preg_match('/^v_[a-f0-9]{32}$/', $value);
}

function issueCookie(string $vid): void
{
    $cookieName = api_env('V_ID_COOKIE_NAME', 'v_id') ?? 'v_id';
    $days = (int) (api_env('V_ID_COOKIE_DAYS', '365') ?? '365');
    if ($days < 1) {
        $days = 365;
    }

    $sameSite = api_env('V_ID_COOKIE_SAMESITE', 'Lax') ?? 'Lax';
    if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
        $sameSite = 'Lax';
    }

    $httpOnly = api_env_bool('V_ID_COOKIE_HTTPONLY', false);

    $secureOverride = api_env('V_ID_COOKIE_SECURE');
    if ($secureOverride === null || $secureOverride === '') {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    } else {
        $secure = api_env_bool('V_ID_COOKIE_SECURE', true);
    }

    setcookie($cookieName, $vid, [
        'expires' => time() + (60 * 60 * 24 * $days),
        'path' => '/',
        'secure' => $secure,
        'httponly' => $httpOnly,
        'samesite' => $sameSite,
    ]);
}

$cookieName = api_env('V_ID_COOKIE_NAME', 'v_id') ?? 'v_id';
$cookieVid = isset($_COOKIE[$cookieName]) ? trim((string) $_COOKIE[$cookieName]) : '';
if (isValidVid($cookieVid)) {
    issueCookie($cookieVid);
    api_ok([
        'v_id' => $cookieVid,
        'source' => 'cookie',
    ]);
}

try {
    $vid = 'v_' . bin2hex(random_bytes(16));
} catch (Throwable $e) {
    api_error('Failed to generate visitor id', 500);
}

$ip = getClientIp();
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

try {
    $pdo = api_pdo();

    $stmt = $pdo->prepare(
        'INSERT INTO visitors (v_id, ip_address, user_agent) VALUES (:v_id, :ip_address, :user_agent)
         ON DUPLICATE KEY UPDATE
           ip_address = VALUES(ip_address),
           user_agent = VALUES(user_agent),
           hit_count = hit_count + 1,
           last_seen = CURRENT_TIMESTAMP'
    );

    $stmt->execute([
        ':v_id' => $vid,
        ':ip_address' => $ip !== '' ? $ip : null,
        ':user_agent' => $userAgent !== '' ? $userAgent : null,
    ]);
} catch (Throwable $e) {
    api_error('Failed to persist visitor id', 500);
}

issueCookie($vid);
api_ok([
    'v_id' => $vid,
    'source' => 'generated',
]);
