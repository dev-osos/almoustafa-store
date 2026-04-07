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

$type = $_GET['type'] ?? '';

function parseDevice(string $ua): array
{
    $device = 'desktop';
    if (preg_match('/iPad|Tablet/i', $ua)) {
        $device = 'tablet';
    } elseif (preg_match('/Mobile|Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
        $device = 'mobile';
    }

    $browser = 'غير معروف';
    if (preg_match('/Edg\//i', $ua))                                    $browser = 'Edge';
    elseif (preg_match('/OPR\//i', $ua))                                $browser = 'Opera';
    elseif (preg_match('/Chrome\/[\d.]+/i', $ua) && !preg_match('/Chromium/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Firefox\/[\d.]+/i', $ua))                      $browser = 'Firefox';
    elseif (preg_match('/Safari\/[\d.]+/i', $ua) && !preg_match('/Chrome/i', $ua))  $browser = 'Safari';
    elseif (preg_match('/MSIE|Trident/i', $ua))                         $browser = 'IE';

    $os = 'غير معروف';
    if (preg_match('/Windows NT/i', $ua))      $os = 'Windows';
    elseif (preg_match('/CrOS/i', $ua))        $os = 'ChromeOS';
    elseif (preg_match('/Android/i', $ua))     $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Mac OS X/i', $ua))    $os = 'macOS';
    elseif (preg_match('/Linux/i', $ua))       $os = 'Linux';

    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}

try {
    $pdo = api_pdo();

    if ($type === 'stats') {
        $total      = (int) $pdo->query('SELECT COUNT(*) FROM visitors')->fetchColumn();
        $today      = (int) $pdo->query("SELECT COUNT(*) FROM visitors WHERE DATE(first_seen) = CURDATE()")->fetchColumn();
        $yesterday  = (int) $pdo->query("SELECT COUNT(*) FROM visitors WHERE DATE(first_seen) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
        $thisWeek   = (int) $pdo->query("SELECT COUNT(*) FROM visitors WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $lastWeek   = (int) $pdo->query("SELECT COUNT(*) FROM visitors WHERE first_seen BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $thisMonth  = (int) $pdo->query("SELECT COUNT(*) FROM visitors WHERE MONTH(first_seen)=MONTH(NOW()) AND YEAR(first_seen)=YEAR(NOW())")->fetchColumn();
        $totalHits  = (int) $pdo->query('SELECT COALESCE(SUM(hit_count),0) FROM visitors')->fetchColumn();
        $online     = (int) $pdo->query("SELECT COUNT(*) FROM visitors WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
        $avgHits    = $total > 0 ? round($totalHits / $total, 1) : 0;

        echo json_encode([
            'total'       => $total,
            'today'       => $today,
            'yesterday'   => $yesterday,
            'thisWeek'    => $thisWeek,
            'lastWeek'    => $lastWeek,
            'thisMonth'   => $thisMonth,
            'totalHits'   => $totalHits,
            'avgHits'     => $avgHits,
            'online'      => $online,
            'growthToday' => $yesterday > 0 ? round(($today - $yesterday) / $yesterday * 100, 1) : null,
            'growthWeek'  => $lastWeek  > 0 ? round(($thisWeek - $lastWeek) / $lastWeek * 100, 1)  : null,
        ]);

    } elseif ($type === 'chart') {
        $days = min(90, max(7, (int)($_GET['days'] ?? 30)));
        $stmt = $pdo->prepare("
            SELECT DATE(first_seen) AS date, COUNT(*) AS count
            FROM visitors
            WHERE first_seen >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(first_seen)
            ORDER BY date ASC
        ");
        $stmt->execute([':days' => $days]);
        echo json_encode($stmt->fetchAll());

    } elseif ($type === 'devices') {
        $rows = $pdo->query("SELECT user_agent, COUNT(*) AS count FROM visitors WHERE user_agent IS NOT NULL GROUP BY user_agent")->fetchAll();
        $agg  = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
        foreach ($rows as $row) {
            $parsed = parseDevice($row['user_agent']);
            $agg[$parsed['device']] += (int) $row['count'];
        }
        echo json_encode($agg);

    } elseif ($type === 'visitors') {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(100, max(10, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $where  = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE ip_address LIKE :s OR v_id LIKE :s OR user_agent LIKE :s';
            $params[':s'] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors $where");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM visitors $where ORDER BY last_seen DESC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $ua = $row['user_agent'] ?? '';
            $row['parsed'] = $ua ? parseDevice($ua) : ['device' => 'unknown', 'browser' => '—', 'os' => '—'];
        }
        unset($row);

        echo json_encode([
            'visitors' => $rows,
            'total'    => $totalCount,
            'page'     => $page,
            'limit'    => $limit,
            'pages'    => (int) ceil($totalCount / $limit),
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown type']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
