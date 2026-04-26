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

    $codeParam = trim((string) ($_GET['code'] ?? ''));
    if ($codeParam !== '') {
        if (!preg_match('/^\d{6}$/', $codeParam)) {
            api_error('كود الدعوة غير صالح', 422);
        }

        $dateFromRaw = trim((string) ($_GET['date_from'] ?? ''));
        $dateToRaw   = trim((string) ($_GET['date_to'] ?? ''));
        $dateFrom = null;
        $dateTo = null;

        if ($dateFromRaw !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromRaw)) {
                api_error('صيغة تاريخ البداية غير صحيحة', 422);
            }
            $dateFrom = $dateFromRaw . ' 00:00:00';
        }
        if ($dateToRaw !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToRaw)) {
                api_error('صيغة تاريخ النهاية غير صحيحة', 422);
            }
            $dateTo = $dateToRaw . ' 23:59:59';
        }
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            api_error('تاريخ البداية يجب أن يكون قبل تاريخ النهاية', 422);
        }

        $sql = "
            SELECT
                COALESCE(SUM(o.total), 0) AS total_spent,
                c.id AS customer_id,
                COALESCE(SUM(o.total), 0) AS customer_spent
            FROM customer_orders o
            INNER JOIN customers c ON c.id = o.customer_id
            WHERE c.referred_by = :code
        ";
        $params = [':code' => $codeParam];
        if ($dateFrom !== null) {
            $sql .= " AND o.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= " AND o.created_at <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        $totalSql = $sql;
        $perCustomerSql = $sql . " GROUP BY c.id";

        $spentStmt = $pdo->prepare($totalSql);
        $spentStmt->execute($params);
        $totalRow = $spentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $totalSpent = (float) ($totalRow['total_spent'] ?? 0);

        $perStmt = $pdo->prepare($perCustomerSql);
        $perStmt->execute($params);
        $perRows = $perStmt->fetchAll(PDO::FETCH_ASSOC);
        $perCustomer = [];
        foreach ($perRows as $row) {
            $cid = (int) ($row['customer_id'] ?? 0);
            if ($cid <= 0) continue;
            $perCustomer[$cid] = round((float) ($row['customer_spent'] ?? 0), 2);
        }

        api_ok([
            'code' => $codeParam,
            'date_from' => $dateFromRaw !== '' ? $dateFromRaw : null,
            'date_to' => $dateToRaw !== '' ? $dateToRaw : null,
            'total_spent' => round($totalSpent, 2),
            'per_customer_spent' => $perCustomer,
        ]);
    }

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
                CONCAT(ref.id, '|', COALESCE(ref.name,''), '|', ref.phone, '|', ref.created_at)
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
                $pieces = explode('|', $part, 4);
                $customers[] = [
                    'id'         => (int) ($pieces[0] ?? 0),
                    'name'       => ($pieces[1] ?? '') ?: 'مستخدم',
                    'phone'      => $pieces[2] ?? '',
                    'created_at' => $pieces[3] ?? '',
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
