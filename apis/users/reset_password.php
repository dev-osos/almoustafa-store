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

    // Fetch customer for auto-login
    $cStmt = $pdo->prepare("SELECT id, name, phone, referral_code, profile_complete, COALESCE(is_blocked, 0) AS is_blocked FROM customers WHERE phone = :phone LIMIT 1");
    $cStmt->execute([':phone' => $phone]);
    $customer = $cStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable) {
    api_error('خطأ في قاعدة البيانات', 500);
}

if ((int) ($customer['is_blocked'] ?? 0) === 1) {
    api_error('هذا الحساب محظور. تواصل مع الدعم.', 403);
}

// Invalidate OTP after use
unset($_SESSION[$sKey]);

// Start authenticated session
session_regenerate_id(true);
$_SESSION['customer_id']    = (int) $customer['id'];
$_SESSION['customer_phone'] = $customer['phone'];
$_SESSION['customer_name']  = $customer['name'] ?? '';
$_SESSION['customer_login_at'] = time();

api_ok([
    'message'          => 'تم تغيير كلمة المرور بنجاح',
    'customer_id'      => (int) $customer['id'],
    'name'             => $customer['name'],
    'phone'            => $customer['phone'],
    'referral_code'    => $customer['referral_code'],
    'profile_complete' => (bool) $customer['profile_complete'],
]);
