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

    // Ensure columns exist (safe if already present)
    $pdo->exec("
        ALTER TABLE customers
            ADD COLUMN IF NOT EXISTS referral_code CHAR(6) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS referred_by   CHAR(6) DEFAULT NULL
    ");

    // Overall stats
    $statsStmt = $pdo->query("
        SELECT
            COUNT(DISTINCT referral_code)                          AS total_codes,
            COUNT(DISTINCT CASE WHEN referred_by IS NOT NULL
                           THEN referred_by END)                   AS active_codes,
            COUNT(CASE WHEN referred_by IS NOT NULL THEN 1 END)    AS total_usage,
            COUNT(DISTINCT CASE WHEN referred_by IS NOT NULL
                           THEN id END)                            AS unique_customers
        FROM customers
        WHERE referral_code IS NOT NULL
    ");
    $overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Each referral code + how many used it + who used it
    $codesStmt = $pdo->query("
        SELECT
            owner.id                                        AS owner_id,
            owner.referral_code                             AS code,
            owner.name                                      AS owner_name,
            owner.phone                                     AS owner_phone,
            owner.created_at                                AS created_at,
            COUNT(ref.id)                                   AS usage_count,
            GROUP_CONCAT(
                CONCAT(COALESCE(ref.name,''), '|', ref.phone, '|', ref.created_at)
                ORDER BY ref.created_at DESC
                SEPARATOR '||'
            )                                               AS customers_data
        FROM customers AS owner
        LEFT JOIN customers AS ref ON ref.referred_by = owner.referral_code
        WHERE owner.referral_code IS NOT NULL
        GROUP BY owner.id, owner.referral_code, owner.name, owner.phone, owner.created_at
        HAVING COUNT(ref.id) >=  0
        ORDER BY COUNT(ref.id) DESC, owner.created_at DESC
    ");
    $codes = $codesStmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedCodes = [];
    foreach ($codes as $row) {
        $customers = [];
        if (!empty($row['customers_data'])) {
            foreach (explode('||', $row['customers_data']) as $part) {
                if ($part === '') continue;
                $pieces = explode('|', $part, 3);
                $customers[] = [
                    'name'       => $pieces[0] ?: 'مستخدم',
                    'phone'      => $pieces[1] ?? '',
                    'created_at' => $pieces[2] ?? '',
                ];
            }
        }

        $formattedCodes[] = [
            'id'             => (int) $row['owner_id'],
            'code'           => $row['code'],
            'owner_name'     => $row['owner_name'] ?: 'مستخدم',
            'owner_phone'    => $row['owner_phone'],
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
            'total_codes'      => (int) $overallStats['total_codes'],
            'active_codes'     => (int) $overallStats['active_codes'],
            'total_usage'      => (int) $overallStats['total_usage'],
            'unique_customers' => (int) $overallStats['unique_customers'],
        ],
        'codes' => $formattedCodes,
    ]);

} catch (Throwable $e) {
    error_log('Invitations stats error: ' . $e->getMessage());
    api_error('خطأ في جلب البيانات: ' . $e->getMessage(), 500);
}
