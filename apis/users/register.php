<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['POST']);

if (session_status() === PHP_SESSION_NONE) session_start();

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$name       = trim((string) ($body['name']        ?? ''));
$phone      = trim((string) ($body['phone']       ?? ''));
$password   = (string) ($body['password']         ?? '');
$referredBy = trim((string) ($body['referred_by'] ?? ''));

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
            referral_code    CHAR(6)       DEFAULT NULL,
            referred_by      CHAR(6)       DEFAULT NULL,
            created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_customers_phone (phone),
            UNIQUE KEY uk_customers_referral (referral_code),
            KEY idx_customers_v_id (v_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // ── Add referral columns to existing tables (MySQL 5.7+ compatible) ─────────
    $hasRefCode = $pdo->query("SHOW COLUMNS FROM customers LIKE 'referral_code'")->fetch();
    if (!$hasRefCode) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN referral_code CHAR(6) DEFAULT NULL");
        try { $pdo->exec("ALTER TABLE customers ADD UNIQUE INDEX uk_customers_referral (referral_code)"); } catch (Throwable) {}
    }
    $hasRefBy = $pdo->query("SHOW COLUMNS FROM customers LIKE 'referred_by'")->fetch();
    if (!$hasRefBy) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN referred_by CHAR(6) DEFAULT NULL");
    }
} catch (Throwable $e) {
    api_error('خطأ في قاعدة البيانات', 500);
}

// ── Check phone not already registered ───────────────────────────────────────
$check = $pdo->prepare("SELECT id FROM customers WHERE phone = :p LIMIT 1");
$check->execute([':p' => $phone]);
if ($check->fetch()) {
    api_error('هذا الرقم مسجّل مسبقاً. يمكنك تسجيل الدخول.', 409);
}

// ── Generate unique 6-digit referral code ─────────────────────────────────────
$referralCode = null;
for ($i = 0; $i < 20; $i++) {
    $candidate = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $chk = $pdo->prepare("SELECT id FROM customers WHERE referral_code = :c LIMIT 1");
    $chk->execute([':c' => $candidate]);
    if (!$chk->fetch()) { $referralCode = $candidate; break; }
}

// ── Validate referred_by code if provided ─────────────────────────────────────
if ($referredBy !== '') {
    if (!preg_match('/^\d{6}$/', $referredBy)) {
        $referredBy = '';
    } else {
        $refChk = $pdo->prepare("SELECT id FROM customers WHERE referral_code = :c LIMIT 1");
        $refChk->execute([':c' => $referredBy]);
        if (!$refChk->fetch()) { $referredBy = ''; }
    }
}

// ── Insert customer ───────────────────────────────────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$vid  = isset($_COOKIE['v_id']) ? trim($_COOKIE['v_id']) : null;

$stmt = $pdo->prepare("
    INSERT INTO customers (name, phone, password_hash, v_id, referral_code, referred_by)
    VALUES (:name, :phone, :hash, :vid, :rcode, :rby)
");
$stmt->execute([
    ':name'  => $name !== '' ? $name : null,
    ':phone' => $phone,
    ':hash'  => $hash,
    ':vid'   => $vid,
    ':rcode' => $referralCode,
    ':rby'   => $referredBy !== '' ? $referredBy : null,
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
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_transactions (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            amount      DECIMAL(12,2)   NOT NULL,
            type        ENUM('credit','debit') NOT NULL,
            reason      VARCHAR(100)    NOT NULL,
            ref_id      BIGINT UNSIGNED DEFAULT NULL,
            created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_wt_customer (customer_id),
            KEY idx_wt_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // Create wallet and add 100 EGP welcome gift
    $pdo->prepare("INSERT IGNORE INTO wallets (customer_id, balance) VALUES (:cid, 100.00)")
        ->execute([':cid' => $customerId]);
    $pdo->prepare("
        INSERT INTO wallet_transactions (customer_id, amount, type, reason)
        VALUES (:cid, 100.00, 'credit', 'welcome_bonus')
    ")->execute([':cid' => $customerId]);
} catch (Throwable) { /* non-fatal */ }

// ── Reward referrer: +15 EGP if a valid referral code was used ───────────────
if ($referredBy !== '') {
    try {
        // Find referrer's customer_id
        $refOwner = $pdo->prepare("SELECT id FROM customers WHERE referral_code = :c LIMIT 1");
        $refOwner->execute([':c' => $referredBy]);
        $referrerId = $refOwner->fetchColumn();

        if ($referrerId) {
            // Ensure referrer has a wallet row, then add 15 EGP
            $pdo->prepare("INSERT IGNORE INTO wallets (customer_id) VALUES (:cid)")
                ->execute([':cid' => $referrerId]);

            $pdo->prepare("UPDATE wallets SET balance = balance + 15.00 WHERE customer_id = :cid")
                ->execute([':cid' => $referrerId]);

            // Log the transaction
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS wallet_transactions (
                    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    customer_id BIGINT UNSIGNED NOT NULL,
                    amount      DECIMAL(12,2)   NOT NULL,
                    type        ENUM('credit','debit') NOT NULL,
                    reason      VARCHAR(100)    NOT NULL,
                    ref_id      BIGINT UNSIGNED DEFAULT NULL,
                    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_wt_customer (customer_id),
                    KEY idx_wt_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->prepare("
                INSERT INTO wallet_transactions (customer_id, amount, type, reason, ref_id)
                VALUES (:cid, 15.00, 'credit', 'referral_bonus', :ref_id)
            ")->execute([':cid' => $referrerId, ':ref_id' => $customerId]);
        }
    } catch (Throwable) { /* non-fatal — registration still succeeds */ }
}

// ── Regenerate session to prevent session fixation ───────────────────────────
session_regenerate_id(true);

// ── Save customer session ─────────────────────────────────────────────────────
$_SESSION['customer_id']    = $customerId;
$_SESSION['customer_phone'] = $phone;
$_SESSION['customer_name']  = $name;

// Clean up OTP session
unset($_SESSION[$sKey], $_SESSION['otp_rate_' . md5($phone)]);

api_ok([
    'customer_id'   => $customerId,
    'phone'         => $phone,
    'name'          => $name ?: null,
    'referral_code' => $referralCode,
    'welcome_gift'  => true,
]);
