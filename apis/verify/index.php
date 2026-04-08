<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';

api_response_init();
api_require_method(['POST']);

// ── Config ────────────────────────────────────────────────────────────────────
const WASENDER_URL = 'https://wasenderapi.com/api/send-message';
const STORE_NAME   = 'متجر المصطفى للعسل';
const CODE_TTL     = 600; // 10 minutes

$wasenderToken = api_env('WASENDER_TOKEN', '');
if ($wasenderToken === '' || $wasenderToken === null) {
    api_error('خدمة الإرسال غير مهيأة', 503);
}

// ── Rate limit via session (no DB needed for OTP) ─────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Parse request ─────────────────────────────────────────────────────────────
$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$phone = trim((string) ($body['phone'] ?? ''));

if ($phone === '') {
    api_error('رقم الهاتف مطلوب', 400);
}

// Normalise phone: strip spaces/dashes/parentheses, ensure leading +
$phone = preg_replace('/[\s\-().]+/', '', $phone);
if (!str_starts_with($phone, '+')) {
    $phone = '+' . ltrim($phone, '0');
}

// Basic international format check: + followed by 7–15 digits
if (!preg_match('/^\+\d{7,15}$/', $phone)) {
    api_error('صيغة رقم الهاتف غير صحيحة. يُرجى إدخال الرقم الدولي مثل: +966501234567', 422);
}

// ── Rate limit: max 3 OTP requests per phone per 10 min ───────────────────────
$rKey    = 'otp_rate_' . md5($phone);
$rData   = $_SESSION[$rKey] ?? ['count' => 0, 'window_start' => time()];

if (time() - $rData['window_start'] > CODE_TTL) {
    $rData = ['count' => 0, 'window_start' => time()];
}
if ($rData['count'] >= 3) {
    api_error('تم تجاوز الحد المسموح. يُرجى الانتظار 10 دقائق قبل المحاولة مجدداً.', 429);
}
$rData['count']++;
$_SESSION[$rKey] = $rData;

// ── Generate 4-digit OTP ──────────────────────────────────────────────────────
try {
    $code = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
} catch (Throwable) {
    api_error('تعذّر توليد كود التحقق', 500);
}

// Store OTP in session (keyed by phone hash)
$sKey = 'otp_' . md5($phone);
$_SESSION[$sKey] = [
    'code'    => $code,
    'phone'   => $phone,
    'expires' => time() + CODE_TTL,
    'used'    => false,
];

// ── Build WhatsApp message ────────────────────────────────────────────────────
$message = implode("\n", [
    '🍯 مرحباً بك في ' . STORE_NAME,
    '',
    'كود التحقق الخاص بتأكيد حسابك هو:',
    '',
    '🔐 *' . $code . '*',
    '',
    '⏱ صالح لمدة 10 دقائق.',
    'لا تُشارك هذا الكود مع أي شخص.',
]);

// ── Send via WasenderAPI ─────────────────────────────────────────────────────
// WasenderAPI expects the number without leading '+', e.g. "966501234567"
$phoneForApi = ltrim($phone, '+');

$payload = json_encode([
    'to'   => $phoneForApi,
    'text' => $message,
]);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", [
            'Authorization: Bearer ' . $wasenderToken,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ]),
        'content' => $payload,
        'timeout' => 15,
        'ignore_errors' => true,
    ],
]);

$raw  = @file_get_contents(WASENDER_URL, false, $ctx);
$meta = $http_response_header ?? [];

// Extract HTTP status code from response headers
$status = 500;
foreach ($meta as $h) {
    if (preg_match('#HTTP/\d+\.?\d*\s+(\d+)#', $h, $m)) {
        $status = (int) $m[1];
        break;
    }
}

if ($raw === false || $status >= 500) {
    // Rollback rate limit count on send failure
    $rData['count']--;
    $_SESSION[$rKey] = $rData;
    api_error('تعذّر إرسال رسالة الواتساب. يُرجى المحاولة لاحقاً.', 503);
}

$response = json_decode((string) $raw, true) ?? [];

if ($status >= 400) {
    $rData['count']--;
    $_SESSION[$rKey] = $rData;
    $detail = $response['message'] ?? $response['error'] ?? 'خطأ من خدمة الإرسال';
    api_error($detail, $status >= 400 && $status < 500 ? 422 : 503);
}

api_ok([
    'message'    => 'تم إرسال كود التحقق إلى ' . $phone,
    'expires_in' => CODE_TTL,
]);
