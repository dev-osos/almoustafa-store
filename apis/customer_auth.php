<?php
declare(strict_types=1);

/**
 * customer_auth.php
 * Require an authenticated, non-deleted, non-blocked customer.
 *
 * Usage:  require_once __DIR__ . '/../customer_auth.php';
 *         // $customerId is now available and guaranteed to exist in DB.
 *
 * Returns 401 JSON and exits if:
 *   - No PHP session / no customer_id in session
 *   - Customer row deleted from DB
 *   - Customer is blocked
 *   - Admin forced logout after session start
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$customerId = (int) ($_SESSION['customer_id'] ?? 0);

if ($customerId === 0) {
    api_error('غير مصادق. يرجى تسجيل الدخول أولاً.', 401);
}

// Verify customer still exists in DB
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

    $stmt = $pdo->prepare("
        SELECT id, COALESCE(is_blocked, 0) AS is_blocked, force_logout_at
        FROM customers
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Customer deleted — destroy server session immediately
        session_unset();
        session_destroy();
        api_error('تم حذف هذا الحساب. يرجى إنشاء حساب جديد.', 401);
    }
    if ((int) ($row['is_blocked'] ?? 0) === 1) {
        session_unset();
        session_destroy();
        api_error('هذا الحساب محظور.', 401);
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
    api_error('خطأ في التحقق من الهوية.', 500);
}
