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
    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.phone, c.segment,
                c.governorate, c.governorate_id, c.city, c.city_id,
                c.address_detail, c.profile_complete, c.referral_code,
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
