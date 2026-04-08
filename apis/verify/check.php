<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';

api_response_init();
api_require_method(['POST']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$phone = trim((string) ($body['phone'] ?? ''));
$code  = trim((string) ($body['code']  ?? ''));

if ($phone === '' || $code === '') {
    api_error('رقم الهاتف والكود مطلوبان', 400);
}

$phone = preg_replace('/[\s\-().]+/', '', $phone);
if (!str_starts_with($phone, '+')) {
    $phone = '+' . ltrim($phone, '0');
}

$sKey = 'otp_' . md5($phone);
$otp  = $_SESSION[$sKey] ?? null;

if (!$otp) {
    api_error('لا يوجد كود تحقق لهذا الرقم. يُرجى طلب كود جديد.', 404);
}

if ($otp['used']) {
    api_error('تم استخدام هذا الكود مسبقاً. يُرجى طلب كود جديد.', 410);
}

if (time() > $otp['expires']) {
    unset($_SESSION[$sKey]);
    api_error('انتهت صلاحية الكود. يُرجى طلب كود جديد.', 410);
}

// ── Brute-force: max 5 wrong attempts per OTP ────────────────────────────────
$failKey  = 'otp_fails_' . md5($phone);
$failCount = (int) ($_SESSION[$failKey] ?? 0);
if ($failCount >= 5) {
    unset($_SESSION[$sKey], $_SESSION[$failKey]);
    api_error('تم تجاوز عدد المحاولات. يُرجى طلب كود جديد.', 429);
}

if (!hash_equals($otp['code'], $code)) {
    $_SESSION[$failKey] = $failCount + 1;
    $remaining = 5 - ($failCount + 1);
    api_error('الكود غير صحيح.' . ($remaining > 0 ? ' المحاولات المتبقية: ' . $remaining : ' سيتم إلغاء الكود.'), 422);
}

// Reset fail counter on success
unset($_SESSION[$failKey]);

// Mark as used (single-use)
$_SESSION[$sKey]['used'] = true;

api_ok(['verified' => true, 'phone' => $phone]);
