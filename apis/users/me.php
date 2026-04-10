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
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $customerId]);

    if (!$stmt->fetch()) {
        // Account deleted — destroy session
        session_unset();
        session_destroy();
        api_error('تم حذف هذا الحساب', 401);
    }
} catch (Throwable) {
    api_error('خطأ في التحقق', 500);
}

api_ok(['alive' => true]);
