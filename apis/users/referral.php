<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);

if (session_status() === PHP_SESSION_NONE) session_start();

$customerId = (int) ($_SESSION['customer_id'] ?? 0);
if ($customerId === 0) {
    api_error('غير مصادق. يرجى تسجيل الدخول أولاً.', 401);
}

try {
    $pdo = api_pdo();

    // Ensure columns exist (safe to run multiple times)
    $pdo->exec("
        ALTER TABLE customers
            ADD COLUMN IF NOT EXISTS referral_code CHAR(6)  DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS referred_by   CHAR(6)  DEFAULT NULL
    ");
    $pdo->exec("
        CREATE UNIQUE INDEX IF NOT EXISTS uk_customers_referral_code
        ON customers (referral_code)
    ");

    // Fetch current user's referral code
    $stmt = $pdo->prepare("SELECT referral_code FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $customerId]);
    $code = $stmt->fetchColumn();

    // Generate if not set
    if (!$code) {
        $attempts = 0;
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $upd  = $pdo->prepare("UPDATE customers SET referral_code = :code WHERE id = :id AND referral_code IS NULL");
            $upd->execute([':code' => $code, ':id' => $customerId]);
            $attempts++;
        } while ($upd->rowCount() === 0 && $attempts < 10);

        // Verify it was saved (handles unique collision)
        $stmt = $pdo->prepare("SELECT referral_code FROM customers WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $customerId]);
        $code = $stmt->fetchColumn();
    }

    // Fetch users who used this referral code
    $refStmt = $pdo->prepare("
        SELECT name, phone, created_at
        FROM customers
        WHERE referred_by = :code
        ORDER BY created_at DESC
    ");
    $refStmt->execute([':code' => $code]);
    $referred = $refStmt->fetchAll(PDO::FETCH_ASSOC);

    // Mask phone numbers for privacy
    $referredList = array_map(function ($row) {
        $phone = (string) $row['phone'];
        $masked = strlen($phone) > 6
            ? substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2)
            : $phone;
        return [
            'name'       => $row['name'] ?: 'مستخدم',
            'phone'      => $masked,
            'joined_at'  => $row['created_at'],
        ];
    }, $referred);

} catch (Throwable $e) {
    api_error('خطأ في قاعدة البيانات', 500);
}

api_ok([
    'referral_code' => $code,
    'referred'      => $referredList,
    'count'         => count($referredList),
]);
