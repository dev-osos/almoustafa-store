<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);

if (session_status() === PHP_SESSION_NONE) session_start();

$customerId = (int) ($_SESSION['customer_id'] ?? 0);

if ($customerId === 0) {
    api_error('غير مصادق', 401);
}

try {
    $pdo  = api_pdo();
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

    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.phone, c.segment,
                c.governorate, c.governorate_id, c.city, c.city_id,
                c.address_detail, c.profile_complete, c.referral_code,
                COALESCE(c.is_blocked, 0) AS is_blocked, c.force_logout_at,
                COALESCE(w.balance, 0) AS wallet_balance
         FROM customers c
         LEFT JOIN wallets w ON w.customer_id = c.id
         WHERE c.id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        session_unset();
        session_destroy();
        api_error('تم حذف هذا الحساب', 401);
    }
    if ((int) ($row['is_blocked'] ?? 0) === 1) {
        session_unset();
        session_destroy();
        api_error('هذا الحساب محظور', 401);
    }
    $sessionLoginAt = (int) ($_SESSION['customer_login_at'] ?? 0);
    $forcedAtRaw = $row['force_logout_at'] ?? null;
    $forcedAtTs = $forcedAtRaw ? strtotime((string) $forcedAtRaw) : false;
    if ($sessionLoginAt > 0 && $forcedAtTs && $sessionLoginAt <= $forcedAtTs) {
        session_unset();
        session_destroy();
        api_error('تم إنهاء جلستك. يرجى تسجيل الدخول مجددًا.', 401);
    }
} catch (Throwable) {
    api_error('خطأ في التحقق', 500);
}

api_ok([
    'alive'            => true,
    'id'               => (int) $row['id'],
    'name'             => $row['name'],
    'phone'            => $row['phone'],
    'segment'          => $row['segment'],
    'governorate'      => $row['governorate'],
    'governorateId'    => $row['governorate_id'] ? (int) $row['governorate_id'] : null,
    'city'             => $row['city'],
    'cityId'           => $row['city_id'] ? (int) $row['city_id'] : null,
    'addressDetails'   => $row['address_detail'],
    'profile_complete' => (bool) $row['profile_complete'],
    'referral_code'    => $row['referral_code'],
    'wallet'           => (float) $row['wallet_balance'],
]);
