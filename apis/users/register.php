<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['POST']);

if (session_status() === PHP_SESSION_NONE) session_start();

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$name     = trim((string) ($body['name']     ?? ''));
$phone    = trim((string) ($body['phone']    ?? ''));
$password = (string) ($body['password'] ?? '');

// ── Normalise phone ───────────────────────────────────────────────────────────
$phone = preg_replace('/[\s\-().]+/', '', $phone);
if (!str_starts_with($phone, '+')) {
    $phone = '+' . ltrim($phone, '0');
}

if (!preg_match('/^\+\d{7,15}$/', $phone)) {
    api_error('صيغة رقم الهاتف غير صحيحة', 422);
}
if (strlen($password) < 6) {
    api_error('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 422);
}

// ── Confirm OTP was verified in this session ──────────────────────────────────
$sKey = 'otp_' . md5($phone);
$otp  = $_SESSION[$sKey] ?? null;
if (!$otp || !$otp['used']) {
    api_error('يجب التحقق من رقم الهاتف أولاً', 403);
}

// ── Auto-create customers table if not exists ─────────────────────────────────
try {
    $pdo = api_pdo();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name             VARCHAR(120)  DEFAULT NULL,
            phone            VARCHAR(20)   NOT NULL,
            password_hash    VARCHAR(255)  NOT NULL,
            segment          ENUM('consumer','wholesale','corporate') NOT NULL DEFAULT 'consumer',
            governorate      VARCHAR(100)  DEFAULT NULL,
            governorate_id   INT UNSIGNED  DEFAULT NULL,
            city             VARCHAR(100)  DEFAULT NULL,
            city_id          INT UNSIGNED  DEFAULT NULL,
            address_detail   TEXT          DEFAULT NULL,
            lat              DECIMAL(10,7) DEFAULT NULL,
            lng              DECIMAL(10,7) DEFAULT NULL,
            profile_complete TINYINT(1)    NOT NULL DEFAULT 0,
            v_id             CHAR(34)      DEFAULT NULL,
            created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_customers_phone (phone),
            KEY idx_customers_v_id (v_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    api_error('خطأ في قاعدة البيانات', 500);
}

// ── Check phone not already registered ───────────────────────────────────────
$check = $pdo->prepare("SELECT id FROM customers WHERE phone = :p LIMIT 1");
$check->execute([':p' => $phone]);
if ($check->fetch()) {
    api_error('هذا الرقم مسجّل مسبقاً. يمكنك تسجيل الدخول.', 409);
}

// ── Insert customer ───────────────────────────────────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$vid  = isset($_COOKIE['v_id']) ? trim($_COOKIE['v_id']) : null;

$stmt = $pdo->prepare("
    INSERT INTO customers (name, phone, password_hash, v_id)
    VALUES (:name, :phone, :hash, :vid)
");
$stmt->execute([
    ':name'  => $name !== '' ? $name : null,
    ':phone' => $phone,
    ':hash'  => $hash,
    ':vid'   => $vid,
]);

$customerId = (int) $pdo->lastInsertId();

// ── Ensure wallets table exists and create wallet for new customer ────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallets (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            balance     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
            created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_wallets_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->prepare("INSERT IGNORE INTO wallets (customer_id) VALUES (:cid)")
        ->execute([':cid' => $customerId]);
} catch (Throwable) { /* non-fatal */ }

// ── Regenerate session to prevent session fixation ───────────────────────────
session_regenerate_id(true);

// ── Save customer session ─────────────────────────────────────────────────────
$_SESSION['customer_id']    = $customerId;
$_SESSION['customer_phone'] = $phone;
$_SESSION['customer_name']  = $name;

// Clean up OTP session
unset($_SESSION[$sKey], $_SESSION['otp_rate_' . md5($phone)]);

api_ok([
    'customer_id' => $customerId,
    'phone'       => $phone,
    'name'        => $name ?: null,
]);
