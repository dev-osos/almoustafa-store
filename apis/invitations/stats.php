<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    api_error('غير مصرح بالوصول', 401);
}

try {
    $pdo = api_pdo();

    // ── Add columns if missing (MySQL 5.7+ compatible) ────────────────────────
    $cols = $pdo->query("SHOW COLUMNS FROM customers LIKE 'referral_code'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN referral_code CHAR(6) DEFAULT NULL");
        try {
            $pdo->exec("ALTER TABLE customers ADD UNIQUE INDEX uk_customers_referral_code (referral_code)");
        } catch (Throwable) { /* index may already exist */ }
    }

    $cols2 = $pdo->query("SHOW COLUMNS FROM customers LIKE 'referred_by'")->fetchAll();
    if (empty($cols2)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN referred_by CHAR(6) DEFAULT NULL");
    }

    // ── Generate referral codes for customers who don't have one ──────────────
    $missing = $pdo->query("SELECT id FROM customers WHERE referral_code IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($missing)) {
        $upd = $pdo->prepare("UPDATE customers SET referral_code = :code WHERE id = :id AND referral_code IS NULL");
        foreach ($missing as $cid) {
            for ($i = 0; $i < 20; $i++) {
                $candidate = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                try {
                    $upd->execute([':code' => $candidate, ':id' => $cid]);
                    if ($upd->rowCount() > 0) break;
                } catch (Throwable) { /* unique collision — retry */ }
            }
        }
    }

    // ── Overall stats ─────────────────────────────────────────────────────────
    $overallStats = $pdo->query("
        SELECT
            COUNT(DISTINCT referral_code)                       AS total_codes,
            COUNT(DISTINCT referred_by)                         AS active_codes,
            COUNT(CASE WHEN referred_by IS NOT NULL THEN 1 END) AS total_usage,
            COUNT(DISTINCT CASE WHEN referred_by IS NOT NULL THEN id END) AS unique_customers
        FROM customers
        WHERE referral_code IS NOT NULL
    ")->fetch(PDO::FETCH_ASSOC);

    // ── Codes with usage >= 1 ─────────────────────────────────────────────────
    $codesStmt = $pdo->query("
        SELECT
            owner.id            AS owner_id,
            owner.referral_code AS code,
            owner.name          AS owner_name,
            owner.phone         AS owner_phone,
            owner.created_at    AS created_at,
            COUNT(ref.id)       AS usage_count,
            GROUP_CONCAT(
                CONCAT(COALESCE(ref.name,''), '|', ref.phone, '|', ref.created_at)
                ORDER BY ref.created_at DESC
                SEPARATOR '||'
            ) AS customers_data
        FROM customers AS owner
        INNER JOIN customers AS ref ON ref.referred_by = owner.referral_code
        WHERE owner.referral_code IS NOT NULL
        GROUP BY owner.id, owner.referral_code, owner.name, owner.phone, owner.created_at
        ORDER BY COUNT(ref.id) DESC, owner.created_at DESC
    ");
    $codes = $codesStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Format response ───────────────────────────────────────────────────────
    $formattedCodes = [];
    foreach ($codes as $row) {
        $customers = [];
        if (!empty($row['customers_data'])) {
            foreach (explode('||', $row['customers_data']) as $part) {
                if ($part === '') continue;
                $pieces = explode('|', $part, 3);
                $customers[] = [
                    'name'       => ($pieces[0] ?? '') ?: 'مستخدم',
                    'phone'      => $pieces[1] ?? '',
                    'created_at' => $pieces[2] ?? '',
                ];
            }
        }
        $formattedCodes[] = [
            'id'             => (int) $row['owner_id'],
            'code'           => $row['code'],
            'owner_name'     => $row['owner_name'] ?: 'مستخدم',
            'owner_phone'    => $row['owner_phone'] ?? '',
            'created_at'     => $row['created_at'],
            'is_active'      => true,
            'max_uses'       => null,
            'usage_count'    => (int) $row['usage_count'],
            'customer_count' => (int) $row['usage_count'],
            'description'    => null,
            'customers'      => $customers,
        ];
    }

    api_ok([
        'stats' => [
            'total_codes'      => (int) ($overallStats['total_codes'] ?? 0),
            'active_codes'     => (int) ($overallStats['active_codes'] ?? 0),
            'total_usage'      => (int) ($overallStats['total_usage'] ?? 0),
            'unique_customers' => (int) ($overallStats['unique_customers'] ?? 0),
        ],
        'codes' => $formattedCodes,
    ]);

} catch (Throwable $e) {
    api_error('خطأ: ' . $e->getMessage(), 500);
}
