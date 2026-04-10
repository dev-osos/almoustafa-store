<?php
declare(strict_types=1);

/**
 * customer_auth.php
 * Require an authenticated, non-deleted customer.
 *
 * Usage:  require_once __DIR__ . '/../customer_auth.php';
 *         // $customerId is now available and guaranteed to exist in DB.
 *
 * Returns 401 JSON and exits if:
 *   - No PHP session / no customer_id in session
 *   - Customer row deleted from DB
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$customerId = (int) ($_SESSION['customer_id'] ?? 0);

if ($customerId === 0) {
    api_error('غير مصادق. يرجى تسجيل الدخول أولاً.', 401);
}

// Verify customer still exists in DB
try {
    $pdo  = api_pdo();
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $customerId]);

    if (!$stmt->fetch()) {
        // Customer deleted — destroy server session immediately
        session_unset();
        session_destroy();
        api_error('تم حذف هذا الحساب. يرجى إنشاء حساب جديد.', 401);
    }
} catch (Throwable) {
    api_error('خطأ في التحقق من الهوية.', 500);
}
