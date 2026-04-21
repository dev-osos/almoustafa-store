<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['POST']);

if (session_status() === PHP_SESSION_NONE) session_start();

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$phone    = trim((string) ($body['phone']    ?? ''));
$password = (string) ($body['password'] ?? '');

if ($phone === '' || $password === '') {
    api_error('رقم الهاتف وكلمة المرور مطلوبان', 400);
}

if (strlen($password) < 6) {
    api_error('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 422);
}

// Normalise phone
$phone = preg_replace('/[\s\-().]+/', '', $phone);
if (!str_starts_with($phone, '+')) {
    $phone = '+' . ltrim($phone, '0');
}
if (!preg_match('/^\+\d{7,15}$/', $phone)) {
    api_error('صيغة رقم الهاتف غير صحيحة', 422);
}

// Confirm OTP was verified in this session
$sKey = 'otp_' . md5($phone);
$otp  = $_SESSION[$sKey] ?? null;
if (!$otp || !$otp['used']) {
    api_error('لم يتم التحقق من رقم الهاتف. يرجى إكمال التحقق أولاً.', 403);
}

try {
    $pdo  = api_pdo();
    $stmt = $pdo->prepare("UPDATE customers SET password_hash = :hash WHERE phone = :phone");
    $stmt->execute([
        ':hash'  => password_hash($password, PASSWORD_BCRYPT),
        ':phone' => $phone,
    ]);
    if ($stmt->rowCount() === 0) {
        api_error('لا يوجد حساب مرتبط بهذا الرقم', 404);
    }
} catch (Throwable) {
    api_error('خطأ في قاعدة البيانات', 500);
}

// Invalidate OTP after use
unset($_SESSION[$sKey]);

api_ok(['message' => 'تم تغيير كلمة المرور بنجاح']);
