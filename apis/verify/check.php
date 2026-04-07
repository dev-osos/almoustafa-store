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

if (!hash_equals($otp['code'], $code)) {
    api_error('الكود غير صحيح.', 422);
}

// Mark as used (single-use)
$_SESSION[$sKey]['used'] = true;

api_ok(['verified' => true, 'phone' => $phone]);
