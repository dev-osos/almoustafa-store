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

    // ── Add columns if missing (MySQL 5.7+ compatible) ────────────────────────
    $cols = $pdo->query("SHOW COLUMNS FROM customers LIKE 'referral_code'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN referral_code CHAR(6) DEFAULT NULL");
        $pdo->exec("ALTER TABLE customers ADD UNIQUE INDEX uk_customers_referral_code (referral_code)");
    }

    $cols2 = $pdo->query("SHOW COLUMNS FROM customers LIKE 'referred_by'")->fetchAll();
    if (empty($cols2)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN referred_by CHAR(6) DEFAULT NULL");
    }

    // ── Fetch or generate referral code for this customer ─────────────────────
    $stmt = $pdo->prepare("SELECT referral_code FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $customerId]);
    $code = $stmt->fetchColumn();

    if (!$code) {
        $attempts = 0;
        do {
            $candidate = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            try {
                $upd = $pdo->prepare("UPDATE customers SET referral_code = :code WHERE id = :id AND referral_code IS NULL");
                $upd->execute([':code' => $candidate, ':id' => $customerId]);
                if ($upd->rowCount() > 0) { $code = $candidate; break; }
            } catch (Throwable) { /* unique collision — retry */ }
            $attempts++;
        } while ($attempts < 20);
    }

    // ── Fetch users who used this code ────────────────────────────────────────
    $referred = [];
    if ($code) {
        $refStmt = $pdo->prepare("
            SELECT name, phone, created_at
            FROM customers
            WHERE referred_by = :code
            ORDER BY created_at DESC
        ");
        $refStmt->execute([':code' => $code]);
        $referred = $refStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mask phone numbers
    $referredList = array_map(function ($row) {
        $phone  = (string) $row['phone'];
        $masked = strlen($phone) > 6
            ? substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2)
            : $phone;
        return [
            'name'      => $row['name'] ?: 'مستخدم',
            'phone'     => $masked,
            'joined_at' => $row['created_at'],
        ];
    }, $referred);

} catch (Throwable $e) {
    api_error('خطأ في قاعدة البيانات: ' . $e->getMessage(), 500);
}

api_ok([
    'referral_code' => $code ?: null,
    'referred'      => $referredList,
    'count'         => count($referredList),
]);
