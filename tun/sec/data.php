<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../apis/db.php';

header('Content-Type: application/json; charset=utf-8');

// ── Role-based permissions ────────────────────────────────────────────────────
const ROLE_PERMS = [
    'super_admin' => ['stats', 'chart', 'devices', 'visitors', 'users', 'geo', 'customer_stats', 'customers', 'customer_orders', 'customer_wallet_tx'],
    'admin'       => ['stats', 'chart', 'devices', 'visitors', 'geo', 'customer_stats', 'customers', 'customer_orders', 'customer_wallet_tx'],
    'support'     => ['visitors', 'geo', 'customer_stats', 'customers', 'customer_orders', 'customer_wallet_tx'],
];

function hasPermission(string $role, string $perm): bool
{
    return in_array($perm, ROLE_PERMS[$role] ?? [], true);
}

function roleLabelAr(string $role): string
{
    return match ($role) {
        'super_admin' => 'سوبر ادمن',
        'admin'       => 'مشرف',
        'support'     => 'دعم فني',
        default       => $role,
    };
}

// ── Single-pass UA parser ─────────────────────────────────────────────────────
function parseDevice(string $ua): array
{
    $device = 'desktop';
    if (preg_match('/iPad|Tablet/i', $ua)) {
        $device = 'tablet';
    } elseif (preg_match('/Mobile|Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
        $device = 'mobile';
    }

    $browser = 'غير معروف';
    if      (preg_match('/Edg\//i', $ua))                                             $browser = 'Edge';
    elseif  (preg_match('/OPR\//i', $ua))                                             $browser = 'Opera';
    elseif  (preg_match('/Chrome\/[\d.]+/i', $ua) && !preg_match('/Chromium/i', $ua)) $browser = 'Chrome';
    elseif  (preg_match('/Firefox\/[\d.]+/i', $ua))                                   $browser = 'Firefox';
    elseif  (preg_match('/Safari\/[\d.]+/i', $ua) && !preg_match('/Chrome/i', $ua))  $browser = 'Safari';
    elseif  (preg_match('/MSIE|Trident/i', $ua))                                      $browser = 'IE';

    $os = 'غير معروف';
    if      (preg_match('/Windows NT/i', $ua))       $os = 'Windows';
    elseif  (preg_match('/CrOS/i', $ua))             $os = 'ChromeOS';
    elseif  (preg_match('/Android/i', $ua))          $os = 'Android';
    elseif  (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
    elseif  (preg_match('/Mac OS X/i', $ua))         $os = 'macOS';
    elseif  (preg_match('/Linux/i', $ua))            $os = 'Linux';

    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}

$role    = $_SESSION['admin_role']    ?? '';
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

try {
    $pdo    = api_pdo();
    $method = $_SERVER['REQUEST_METHOD'];

    // ── POST — actions ────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';

        // ── Reviews management (admin + super_admin) ──────────────────────────
        if (in_array($action, ['review_toggle', 'review_delete', 'review_reorder'], true)) {
            if (!in_array($role, ['super_admin', 'admin'], true)) {
                http_response_code(403);
                echo json_encode(['error' => 'ممنوع: صلاحيات غير كافية']);
                exit;
            }
            $reviewId = (int) ($body['id'] ?? 0);

            if ($action === 'review_toggle') {
                if ($reviewId === 0) { http_response_code(400); echo json_encode(['error' => 'معرّف غير صحيح']); exit; }
                $stmt = $pdo->prepare("UPDATE reviews SET visible = 1 - visible WHERE id = :id");
                $stmt->execute([':id' => $reviewId]);
                $vis = (int) $pdo->query("SELECT visible FROM reviews WHERE id = {$reviewId}")->fetchColumn();
                echo json_encode(['ok' => true, 'visible' => (bool) $vis]);

            } elseif ($action === 'review_delete') {
                if ($reviewId === 0) { http_response_code(400); echo json_encode(['error' => 'معرّف غير صحيح']); exit; }
                $pdo->prepare("DELETE FROM reviews WHERE id = :id")->execute([':id' => $reviewId]);
                echo json_encode(['ok' => true]);

            } elseif ($action === 'review_reorder') {
                $ids = array_map('intval', $body['ids'] ?? []);
                if (empty($ids)) { echo json_encode(['ok' => true]); exit; }
                $total = count($ids);
                $stmt  = $pdo->prepare("UPDATE reviews SET sort_order = :s WHERE id = :id");
                foreach ($ids as $i => $id) {
                    $stmt->execute([':s' => $total - $i, ':id' => $id]);
                }
                echo json_encode(['ok' => true]);
            }
            exit;
        }

        // ── Customer moderation (support/admin/super_admin) ───────────────────
        if ($action === 'toggle_customer_block') {
            if (!hasPermission($role, 'customers')) {
                http_response_code(403);
                echo json_encode(['error' => 'ممنوع: صلاحيات غير كافية']);
                exit;
            }

            $customerId = (int) ($body['customer_id'] ?? 0);
            if ($customerId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'معرّف عميل غير صحيح']);
                exit;
            }

            try {
                $hasBlocked = $pdo->query("SHOW COLUMNS FROM customers LIKE 'is_blocked'")->fetch();
                if (!$hasBlocked) {
                    $pdo->exec("ALTER TABLE customers ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0");
                }
                $hasForceLogout = $pdo->query("SHOW COLUMNS FROM customers LIKE 'force_logout_at'")->fetch();
                if (!$hasForceLogout) {
                    $pdo->exec("ALTER TABLE customers ADD COLUMN force_logout_at DATETIME NULL DEFAULT NULL");
                }
            } catch (Throwable) {
                http_response_code(500);
                echo json_encode(['error' => 'تعذر تجهيز أعمدة الحظر']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE customers
                SET
                    is_blocked = IF(is_blocked = 1, 0, 1),
                    force_logout_at = IF(is_blocked = 1, NULL, NOW())
                WHERE id = :id
            ");
            $stmt->execute([':id' => $customerId]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'العميل غير موجود']);
                exit;
            }

            $stateStmt = $pdo->prepare("SELECT is_blocked FROM customers WHERE id = :id LIMIT 1");
            $stateStmt->execute([':id' => $customerId]);
            $isBlocked = (int) $stateStmt->fetchColumn() === 1;
            echo json_encode(['ok' => true, 'is_blocked' => $isBlocked]);
            exit;
        }

        if ($action === 'update_customer_profile') {
            if (!hasPermission($role, 'customers')) {
                http_response_code(403);
                echo json_encode(['error' => 'ممنوع: صلاحيات غير كافية']);
                exit;
            }

            $customerId = (int) ($body['customer_id'] ?? 0);
            $name = trim((string) ($body['name'] ?? ''));
            $phone = trim((string) ($body['phone'] ?? ''));
            $segment = trim((string) ($body['segment'] ?? 'consumer'));
            $governorate = trim((string) ($body['governorate'] ?? ''));
            $city = trim((string) ($body['city'] ?? ''));
            $addressDetail = trim((string) ($body['address_detail'] ?? ''));

            if ($customerId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'معرّف عميل غير صحيح']);
                exit;
            }
            if ($phone === '') {
                http_response_code(400);
                echo json_encode(['error' => 'رقم الهاتف مطلوب']);
                exit;
            }
            if (!in_array($segment, ['consumer', 'wholesale', 'corporate'], true)) {
                $segment = 'consumer';
            }

            $phone = preg_replace('/[\s\-().]+/', '', $phone);
            if (!str_starts_with($phone, '+')) $phone = '+' . ltrim($phone, '0');
            if (!preg_match('/^\+\d{7,15}$/', $phone)) {
                http_response_code(422);
                echo json_encode(['error' => 'صيغة رقم الهاتف غير صحيحة']);
                exit;
            }

            $dup = $pdo->prepare("SELECT id FROM customers WHERE phone = :phone AND id <> :id LIMIT 1");
            $dup->execute([':phone' => $phone, ':id' => $customerId]);
            if ($dup->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'رقم الهاتف مستخدم لعميل آخر']);
                exit;
            }

            $profileComplete = ($name !== '' && $governorate !== '' && $city !== '' && $addressDetail !== '') ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE customers
                SET
                    name = :name,
                    phone = :phone,
                    segment = :segment,
                    governorate = :governorate,
                    city = :city,
                    address_detail = :address_detail,
                    profile_complete = :profile_complete
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name !== '' ? $name : null,
                ':phone' => $phone,
                ':segment' => $segment,
                ':governorate' => $governorate !== '' ? $governorate : null,
                ':city' => $city !== '' ? $city : null,
                ':address_detail' => $addressDetail !== '' ? $addressDetail : null,
                ':profile_complete' => $profileComplete,
                ':id' => $customerId,
            ]);
            if ($stmt->rowCount() === 0) {
                $exists = $pdo->prepare("SELECT id FROM customers WHERE id = :id LIMIT 1");
                $exists->execute([':id' => $customerId]);
                if (!$exists->fetch()) {
                    http_response_code(404);
                    echo json_encode(['error' => 'العميل غير موجود']);
                    exit;
                }
            }

            echo json_encode(['ok' => true, 'profile_complete' => (bool) $profileComplete]);
            exit;
        }

        if ($action === 'adjust_customer_wallet') {
            if (!hasPermission($role, 'customers')) {
                http_response_code(403);
                echo json_encode(['error' => 'ممنوع: صلاحيات غير كافية']);
                exit;
            }

            $customerId = (int) ($body['customer_id'] ?? 0);
            $mode = (string) ($body['mode'] ?? 'add'); // add | subtract | set
            $amount = (float) ($body['amount'] ?? 0);
            $reason = trim((string) ($body['reason'] ?? 'manual_adjustment'));

            if ($customerId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'معرّف عميل غير صحيح']);
                exit;
            }
            if (!in_array($mode, ['add', 'subtract', 'set'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'نوع العملية غير صحيح']);
                exit;
            }
            if ($amount < 0 || ($mode !== 'set' && $amount <= 0)) {
                http_response_code(400);
                echo json_encode(['error' => 'قيمة المبلغ غير صحيحة']);
                exit;
            }

            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS wallets (
                        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        customer_id BIGINT UNSIGNED NOT NULL,
                        balance     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
                        created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_wallets_customer (customer_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS wallet_transactions (
                        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        customer_id BIGINT UNSIGNED NOT NULL,
                        amount      DECIMAL(12,2)   NOT NULL,
                        type        ENUM('credit','debit') NOT NULL,
                        reason      VARCHAR(100)    NOT NULL,
                        ref_id      BIGINT UNSIGNED DEFAULT NULL,
                        created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_wt_customer (customer_id),
                        KEY idx_wt_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (Throwable) { /* non-fatal */ }

            $existsStmt = $pdo->prepare("SELECT id FROM customers WHERE id = :id LIMIT 1");
            $existsStmt->execute([':id' => $customerId]);
            if (!$existsStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'العميل غير موجود']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT IGNORE INTO wallets (customer_id, balance) VALUES (:cid, 0.00)")
                    ->execute([':cid' => $customerId]);

                $balStmt = $pdo->prepare("SELECT balance FROM wallets WHERE customer_id = :cid LIMIT 1 FOR UPDATE");
                $balStmt->execute([':cid' => $customerId]);
                $oldBal = (float) ($balStmt->fetchColumn() ?: 0);
                $newBal = $oldBal;
                $txType = 'credit';
                $txAmount = $amount;

                if ($mode === 'add') {
                    $newBal = $oldBal + $amount;
                    $txType = 'credit';
                } elseif ($mode === 'subtract') {
                    if ($amount > $oldBal) {
                        $pdo->rollBack();
                        http_response_code(400);
                        echo json_encode(['error' => 'لا يمكن خصم مبلغ أكبر من الرصيد الحالي']);
                        exit;
                    }
                    $newBal = $oldBal - $amount;
                    $txType = 'debit';
                } else { // set
                    $newBal = $amount;
                    $diff = $newBal - $oldBal;
                    $txType = $diff >= 0 ? 'credit' : 'debit';
                    $txAmount = abs($diff);
                }

                $up = $pdo->prepare("UPDATE wallets SET balance = :bal WHERE customer_id = :cid");
                $up->execute([':bal' => $newBal, ':cid' => $customerId]);

                if ($txAmount > 0) {
                    $insTx = $pdo->prepare("
                        INSERT INTO wallet_transactions (customer_id, amount, type, reason)
                        VALUES (:cid, :amt, :type, :reason)
                    ");
                    $insTx->execute([
                        ':cid' => $customerId,
                        ':amt' => round($txAmount, 2),
                        ':type' => $txType,
                        ':reason' => 'admin_adjustment:' . substr($reason !== '' ? $reason : 'manual_adjustment', 0, 80),
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'تعذر تحديث رصيد المحفظة']);
                exit;
            }

            echo json_encode([
                'ok' => true,
                'old_balance' => round($oldBal, 2),
                'new_balance' => round($newBal, 2),
            ]);
            exit;
        }

        // ── User management (super_admin only) ───────────────────────────────
        if (!hasPermission($role, 'users')) {
            http_response_code(403);
            echo json_encode(['error' => 'ممنوع: صلاحيات غير كافية']);
            exit;
        }

        if ($action === 'create_user') {
            $username = trim($body['username'] ?? '');
            $password = $body['password'] ?? '';
            $newRole  = $body['role'] ?? '';

            if ($username === '' || strlen($password) < 1 || !in_array($newRole, ['super_admin', 'admin', 'support'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'بيانات غير صحيحة']);
                exit;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                INSERT INTO dashboard_users (username, password_hash, role, created_by)
                VALUES (:u, :h, :r, :cb)
            ");
            $stmt->execute([':u' => $username, ':h' => $hash, ':r' => $newRole, ':cb' => $adminId]);
            echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);

        } elseif ($action === 'toggle_user') {
            $targetId = (int) ($body['id'] ?? 0);
            if ($targetId === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'معرّف غير صحيح']);
                exit;
            }
            if ($targetId === $adminId) {
                http_response_code(400);
                echo json_encode(['error' => 'لا يمكنك تعطيل حسابك الخاص']);
                exit;
            }
            $stmt = $pdo->prepare("
                UPDATE dashboard_users
                SET is_active = IF(is_active = 1, 0, 1)
                WHERE id = :id
            ");
            $stmt->execute([':id' => $targetId]);
            echo json_encode(['ok' => true]);

        } elseif ($action === 'delete_user') {
            $targetId = (int) ($body['id'] ?? 0);
            if ($targetId === 0 || $targetId === $adminId) {
                http_response_code(400);
                echo json_encode(['error' => 'لا يمكن حذف هذا الحساب']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM dashboard_users WHERE id = :id");
            $stmt->execute([':id' => $targetId]);
            echo json_encode(['ok' => true]);

        } else {
            http_response_code(400);
            echo json_encode(['error' => 'إجراء غير معروف']);
        }
        exit;
    }

    // ── GET ───────────────────────────────────────────────────────────────────
    $type = $_GET['type'] ?? '';

    // ── Reviews (admin + super_admin) ─────────────────────────────────────────
    if ($type === 'reviews') {
        if (!in_array($role, ['super_admin', 'admin'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'ممنوع']);
            exit;
        }
        $stmt = $pdo->query("
            SELECT id, name, product, rating, content, visible, sort_order, created_at
            FROM reviews
            ORDER BY sort_order DESC, created_at DESC
        ");
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'reviews' => $reviews]);
        exit;
    }

    if (!hasPermission($role, $type)) {
        http_response_code(403);
        echo json_encode(['error' => 'ممنوع: صلاحيات غير كافية']);
        exit;
    }

    // ── STATS — single table scan with conditional aggregation ────────────────
    if ($type === 'stats') {

        $row = $pdo->query("
            SELECT
                COUNT(*)                                                          AS total,
                COALESCE(SUM(hit_count), 0)                                       AS total_hits,
                SUM(first_seen >= CURDATE())                                      AS today,
                SUM(first_seen >= CURDATE() - INTERVAL 1 DAY
                    AND first_seen <  CURDATE())                                  AS yesterday,
                SUM(first_seen >= NOW() - INTERVAL 7 DAY)                         AS this_week,
                SUM(first_seen >= NOW() - INTERVAL 14 DAY
                    AND first_seen <  NOW() - INTERVAL 7 DAY)                    AS last_week,
                SUM(first_seen >= DATE_FORMAT(NOW(), '%Y-%m-01'))                 AS this_month,
                SUM(last_seen  >= NOW() - INTERVAL 5 MINUTE)                     AS online
            FROM visitors
        ")->fetch();

        $total     = (int) $row['total'];
        $today     = (int) $row['today'];
        $yesterday = (int) $row['yesterday'];
        $thisWeek  = (int) $row['this_week'];
        $lastWeek  = (int) $row['last_week'];
        $totalHits = (int) $row['total_hits'];

        echo json_encode([
            'total'       => $total,
            'today'       => $today,
            'yesterday'   => $yesterday,
            'thisWeek'    => $thisWeek,
            'lastWeek'    => $lastWeek,
            'thisMonth'   => (int) $row['this_month'],
            'totalHits'   => $totalHits,
            'avgHits'     => $total > 0 ? round($totalHits / $total, 1) : 0,
            'online'      => (int) $row['online'],
            'growthToday' => $yesterday > 0 ? round(($today    - $yesterday) / $yesterday * 100, 1) : null,
            'growthWeek'  => $lastWeek  > 0 ? round(($thisWeek - $lastWeek)  / $lastWeek  * 100, 1) : null,
        ]);

    // ── CHART ─────────────────────────────────────────────────────────────────
    } elseif ($type === 'chart') {

        $days = min(90, max(7, (int)($_GET['days'] ?? 30)));
        $stmt = $pdo->prepare("
            SELECT DATE(first_seen) AS date, COUNT(*) AS count
            FROM   visitors
            WHERE  first_seen >= CURDATE() - INTERVAL :days DAY
            GROUP  BY DATE(first_seen)
            ORDER  BY date ASC
        ");
        $stmt->execute([':days' => $days]);
        echo json_encode($stmt->fetchAll());

    // ── DEVICES ───────────────────────────────────────────────────────────────
    } elseif ($type === 'devices') {

        $rows = $pdo->query("
            SELECT user_agent, COUNT(*) AS cnt
            FROM   visitors
            WHERE  user_agent IS NOT NULL
            GROUP  BY user_agent
        ")->fetchAll();

        $agg = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
        foreach ($rows as $row) {
            $agg[parseDevice($row['user_agent'])['device']] += (int) $row['cnt'];
        }
        echo json_encode($agg);

    // ── VISITORS ──────────────────────────────────────────────────────────────
    } elseif ($type === 'visitors') {

        $page   = max(1, (int)($_GET['page']  ?? 1));
        $limit  = min(100, max(10, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $where  = '';
        $params = [];
        if ($search !== '') {
            $where        = 'WHERE ip_address LIKE :s OR v_id LIKE :s OR user_agent LIKE :s';
            $params[':s'] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors $where");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetchColumn();

        $dataStmt = $pdo->prepare("
            SELECT id, v_id, ip_address, user_agent, first_seen, last_seen, hit_count
            FROM   visitors
            $where
            ORDER  BY last_seen DESC
            LIMIT  :lim OFFSET :off
        ");
        $dataStmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $dataStmt->bindValue(':off', $offset, PDO::PARAM_INT);
        foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        foreach ($rows as &$row) {
            $ua            = $row['user_agent'] ?? '';
            $row['parsed'] = $ua ? parseDevice($ua) : ['device' => 'unknown', 'browser' => '—', 'os' => '—'];
            unset($row['user_agent']);
        }
        unset($row);

        echo json_encode([
            'visitors' => $rows,
            'total'    => $totalCount,
            'page'     => $page,
            'limit'    => $limit,
            'pages'    => (int) ceil($totalCount / $limit),
        ]);

    // ── USERS — super_admin only ──────────────────────────────────────────────
    } elseif ($type === 'users') {

        $rows = $pdo->query("
            SELECT  u.id,
                    u.username,
                    u.role,
                    u.is_active,
                    u.created_at,
                    c.username AS created_by_name
            FROM    dashboard_users u
            LEFT JOIN dashboard_users c ON c.id = u.created_by
            ORDER   BY u.created_at ASC
        ")->fetchAll();

        foreach ($rows as &$r) {
            $r['role_label'] = roleLabelAr($r['role']);
            $r['is_self']    = ((int) $r['id']) === $adminId;
        }
        unset($r);

        echo json_encode($rows);

    // ── CUSTOMER STATS — single scan with conditional aggregation ────────────
    } elseif ($type === 'customer_stats') {

        try {
            $row = $pdo->query("
                SELECT
                    COUNT(*)                                                              AS total,
                    SUM(created_at >= CURDATE())                                          AS today,
                    SUM(created_at >= CURDATE() - INTERVAL 1 DAY
                        AND created_at < CURDATE())                                       AS yesterday,
                    SUM(created_at >= NOW()     - INTERVAL 7 DAY)                         AS this_week,
                    SUM(created_at >= NOW()     - INTERVAL 14 DAY
                        AND created_at < NOW()  - INTERVAL 7 DAY)                        AS last_week,
                    SUM(profile_complete = 1)                                             AS complete,
                    SUM(profile_complete = 0)                                             AS incomplete,
                    SUM(segment IN ('wholesale','corporate'))                             AS wholesale
                FROM customers
            ")->fetch();
        } catch (Throwable) {
            // Table may not exist yet
            $row = ['total'=>0,'today'=>0,'yesterday'=>0,'this_week'=>0,'last_week'=>0,'complete'=>0,'incomplete'=>0,'wholesale'=>0];
        }

        $today    = (int) $row['today'];
        $yest     = (int) $row['yesterday'];
        $thisWeek = (int) $row['this_week'];
        $lastWeek = (int) $row['last_week'];

        echo json_encode([
            'total'        => (int) $row['total'],
            'today'        => $today,
            'this_week'    => $thisWeek,
            'complete'     => (int) $row['complete'],
            'incomplete'   => (int) $row['incomplete'],
            'wholesale'    => (int) $row['wholesale'],
            'growth_today' => $yest     > 0 ? round(($today    - $yest)     / $yest     * 100, 1) : null,
            'growth_week'  => $lastWeek > 0 ? round(($thisWeek - $lastWeek) / $lastWeek * 100, 1) : null,
        ]);

    // ── CUSTOMERS — paginated list ────────────────────────────────────────────
    } elseif ($type === 'customers') {

        $page    = max(1, (int)($_GET['page']  ?? 1));
        $limit   = min(100, max(10, (int)($_GET['limit'] ?? 25)));
        $offset  = ($page - 1) * $limit;
        $search  = trim($_GET['search']  ?? '');
        $segment = trim($_GET['segment'] ?? '');
        $profile = $_GET['profile'] ?? '';

        $conds  = [];
        $params = [];

        if ($search !== '') {
            $conds[]      = '(name LIKE :s OR phone LIKE :s)';
            $params[':s'] = '%' . $search . '%';
        }
        if (in_array($segment, ['consumer','wholesale','corporate'], true)) {
            $conds[]          = 'segment = :seg';
            $params[':seg']   = $segment;
        }
        if ($profile === '1' || $profile === '0') {
            $conds[]           = 'profile_complete = :pc';
            $params[':pc']     = (int) $profile;
        }

        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        // Ensure wallets table exists (lazy creation)
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS wallets (
                    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    customer_id BIGINT UNSIGNED NOT NULL,
                    balance     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
                    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_wallets_customer (customer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            // Back-fill wallets for any existing customers that don't have one
            $pdo->exec("
                INSERT IGNORE INTO wallets (customer_id)
                SELECT id FROM customers
            ");
            $hasBlocked = $pdo->query("SHOW COLUMNS FROM customers LIKE 'is_blocked'")->fetch();
            if (!$hasBlocked) {
                $pdo->exec("ALTER TABLE customers ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0");
            }
            $hasForceLogout = $pdo->query("SHOW COLUMNS FROM customers LIKE 'force_logout_at'")->fetch();
            if (!$hasForceLogout) {
                $pdo->exec("ALTER TABLE customers ADD COLUMN force_logout_at DATETIME NULL DEFAULT NULL");
            }
        } catch (Throwable) { /* non-fatal */ }

        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers $where");
            $countStmt->execute($params);
            $totalCount = (int) $countStmt->fetchColumn();

            $dataStmt = $pdo->prepare("
                SELECT c.id, c.name, c.phone, c.segment, c.governorate, c.city, c.address_detail,
                       c.profile_complete, c.created_at, COALESCE(c.is_blocked, 0) AS is_blocked,
                       COALESCE(w.balance, 0.00) AS wallet_balance
                FROM   customers c
                LEFT JOIN wallets w ON w.customer_id = c.id
                $where
                ORDER  BY c.created_at DESC
                LIMIT  :lim OFFSET :off
            ");
            $dataStmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $dataStmt->bindValue(':off', $offset, PDO::PARAM_INT);
            foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
            $dataStmt->execute();
            $rows = $dataStmt->fetchAll();
        } catch (Throwable) {
            $rows = []; $totalCount = 0;
        }

        echo json_encode([
            'customers' => $rows,
            'total'     => $totalCount,
            'page'      => $page,
            'limit'     => $limit,
            'pages'     => (int) ceil($totalCount / max(1, $limit)),
        ]);

    // ── CUSTOMER ORDERS — details + purchase stats ────────────────────────────
    } elseif ($type === 'customer_orders') {

        $customerId = (int) ($_GET['customer_id'] ?? 0);
        if ($customerId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'معرّف عميل غير صحيح']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT id, order_number, status, items_json,
                       subtotal, shipping, wallet_discount, total,
                       note, created_at
                FROM customer_orders
                WHERE customer_id = :cid
                ORDER BY created_at DESC, id DESC
                LIMIT 200
            ");
            $stmt->execute([':cid' => $customerId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $rows = [];
        }

        $orders = [];
        $totalSpent = 0.0;
        $totalItems = 0;
        $statusCounts = [
            'pending' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];

        foreach ($rows as $row) {
            $items = json_decode((string) ($row['items_json'] ?? '[]'), true);
            if (!is_array($items)) $items = [];

            $itemCount = 0;
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $qty = (int) ($it['qty'] ?? 0);
                if ($qty > 0) $itemCount += $qty;
            }

            $status = (string) ($row['status'] ?? 'pending');
            if (!isset($statusCounts[$status])) $statusCounts[$status] = 0;
            $statusCounts[$status]++;

            $total = (float) ($row['total'] ?? 0);
            if ($status !== 'cancelled') $totalSpent += $total;
            $totalItems += $itemCount;

            $orders[] = [
                'id' => (int) ($row['id'] ?? 0),
                'order_number' => (string) ($row['order_number'] ?? ''),
                'status' => $status,
                'item_count' => $itemCount,
                'subtotal' => (float) ($row['subtotal'] ?? 0),
                'shipping' => (float) ($row['shipping'] ?? 0),
                'wallet_discount' => (float) ($row['wallet_discount'] ?? 0),
                'total' => $total,
                'note' => (string) ($row['note'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        echo json_encode([
            'ok' => true,
            'orders' => $orders,
            'stats' => [
                'orders_count' => count($orders),
                'total_spent' => round($totalSpent, 2),
                'items_count' => $totalItems,
                'last_order_at' => $orders[0]['created_at'] ?? null,
                'status_counts' => $statusCounts,
            ],
        ]);

    // ── CUSTOMER WALLET TRANSACTIONS — latest movements ───────────────────────
    } elseif ($type === 'customer_wallet_tx') {

        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $limit = min(30, max(5, (int) ($_GET['limit'] ?? 10)));
        if ($customerId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'معرّف عميل غير صحيح']);
            exit;
        }

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS wallet_transactions (
                    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    customer_id BIGINT UNSIGNED NOT NULL,
                    amount      DECIMAL(12,2)   NOT NULL,
                    type        ENUM('credit','debit') NOT NULL,
                    reason      VARCHAR(100)    NOT NULL,
                    ref_id      BIGINT UNSIGNED DEFAULT NULL,
                    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_wt_customer (customer_id),
                    KEY idx_wt_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable) { /* non-fatal */ }

        try {
            $stmt = $pdo->prepare("
                SELECT id, amount, type, reason, created_at
                FROM wallet_transactions
                WHERE customer_id = :cid
                ORDER BY created_at DESC, id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':cid', $customerId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $rows = [];
        }

        echo json_encode(['ok' => true, 'transactions' => $rows]);

    // ── GEO — server-side IP lookup proxy (avoids browser CORS block) ────────
    } elseif ($type === 'geo') {

        $raw = trim($_GET['ips'] ?? '');
        if ($raw === '') {
            echo json_encode([]);
            exit;
        }

        // Sanitize: keep only valid-looking IPs, max 50
        $ips = array_slice(
            array_filter(
                array_map('trim', explode(',', $raw)),
                fn($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false
            ),
            0,
            50
        );

        if (empty($ips)) {
            echo json_encode([]);
            exit;
        }

        $payload = json_encode(array_map(fn($ip) => ['query' => $ip], $ips));
        $ctx     = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nUser-Agent: PHP/GeoProxy\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $raw = @file_get_contents(
            'http://ip-api.com/batch?fields=query,country,city,status',
            false,
            $ctx
        );

        if ($raw === false) {
            echo json_encode([]);
            exit;
        }

        $results = json_decode($raw, true) ?? [];
        $map     = [];
        foreach ($results as $r) {
            if (($r['status'] ?? '') === 'success') {
                $map[$r['query']] = ['country' => $r['country'], 'city' => $r['city']];
            } else {
                $map[$r['query']] = null;
            }
        }
        echo json_encode($map);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown type']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
