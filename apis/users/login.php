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

// ── Normalise phone ───────────────────────────────────────────────────────────
$phone = preg_replace('/[\s\-().]+/', '', $phone);
if (!str_starts_with($phone, '+')) {
    $phone = '+' . ltrim($phone, '0');
}

if (!preg_match('/^\+\d{7,15}$/', $phone)) {
    api_error('صيغة رقم الهاتف غير صحيحة', 422);
}

// ── Brute-force protection (DB-based, keyed by phone + IP) ───────────────────
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_SEC   = 900; // 15 minutes

try {
    $pdo = api_pdo();
    try {
        $hasBlocked = $pdo->query("SHOW COLUMNS FROM customers LIKE 'is_blocked'")->fetch();
        if (!$hasBlocked) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0");
        }
        $hasForceLogout = $pdo->query("SHOW COLUMNS FROM customers LIKE 'force_logout_at'")->fetch();
        if (!$hasForceLogout) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN force_logout_at DATETIME NULL DEFAULT NULL");
        }
    } catch (Throwable) { /* non-fatal */ }

    // Ensure login_attempts table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone      VARCHAR(20)  NOT NULL,
            ip         VARCHAR(45)  NOT NULL,
            attempted_at TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_la_phone_time (phone, attempted_at),
            KEY idx_la_ip_time    (ip,    attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ip      = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $cutoff  = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SEC);

    // Count recent failures for this phone OR this IP
    $cntStmt = $pdo->prepare("
        SELECT COUNT(id) FROM login_attempts
        WHERE (phone = :phone OR ip = :ip)
          AND attempted_at > :cutoff
    ");
    $cntStmt->execute([':phone' => $phone, ':ip' => $ip, ':cutoff' => $cutoff]);
    $attempts = (int) $cntStmt->fetchColumn();

    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        api_error('تم تجاوز عدد المحاولات المسموح بها. يُرجى الانتظار 15 دقيقة.', 429);
    }

} catch (Throwable) {
    api_error('خطأ في قاعدة البيانات', 500);
}

// ── Fetch customer ────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT id, name, phone, password_hash, profile_complete, referral_code,
               COALESCE(is_blocked, 0) AS is_blocked
        FROM customers
        WHERE phone = :phone
        LIMIT 1
    ");
    $stmt->execute([':phone' => $phone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable) {
    api_error('خطأ في قاعدة البيانات', 500);
}

// ── Verify password (timing-safe via password_verify) ────────────────────────
$validCredentials = $customer && password_verify($password, (string) $customer['password_hash']);

if (!$validCredentials) {
    // Record failed attempt
    try {
        $ins = $pdo->prepare("INSERT INTO login_attempts (phone, ip) VALUES (:phone, :ip)");
        $ins->execute([':phone' => $phone, ':ip' => $ip]);
    } catch (Throwable) { /* non-fatal */ }

    // Generic message — do NOT reveal whether phone exists
    api_error('رقم الهاتف أو كلمة المرور غير صحيحة', 401);
}
if ((int) ($customer['is_blocked'] ?? 0) === 1) {
    api_error('تم حظر هذا الحساب. تواصل مع الدعم.', 403);
}

// ── Clear old attempts on successful login ────────────────────────────────────
try {
    $del = $pdo->prepare("DELETE FROM login_attempts WHERE phone = :phone");
    $del->execute([':phone' => $phone]);
} catch (Throwable) { /* non-fatal */ }

// ── Regenerate session to prevent session fixation ───────────────────────────
session_regenerate_id(true);

$_SESSION['customer_id']    = (int) $customer['id'];
$_SESSION['customer_phone'] = $customer['phone'];
$_SESSION['customer_name']  = $customer['name'] ?? '';
$_SESSION['customer_login_at'] = time();

api_ok([
    'customer_id'      => (int) $customer['id'],
    'phone'            => $customer['phone'],
    'name'             => $customer['name'],
    'profile_complete' => (bool) $customer['profile_complete'],
    'referral_code'    => $customer['referral_code'],
]);
