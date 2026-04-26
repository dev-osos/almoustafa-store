<?php
declare(strict_types=1);

session_start();
if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$pdo = api_pdo();

// Ensure table supports guest orders (nullable customer_id)
try {
    $col = $pdo->query("SHOW COLUMNS FROM customer_orders LIKE 'customer_id'")->fetch(PDO::FETCH_ASSOC);
    if ($col && str_contains(strtolower((string)($col['Null'] ?? '')), 'no')) {
        $pdo->exec("ALTER TABLE customer_orders MODIFY COLUMN customer_id BIGINT UNSIGNED NULL DEFAULT NULL");
    }
} catch (Throwable) {}

// Ensure guest_name / guest_phone columns exist
try {
    $hasGuest = $pdo->query("SHOW COLUMNS FROM customer_orders LIKE 'is_guest'")->fetch();
    if (!$hasGuest) {
        $pdo->exec("ALTER TABLE customer_orders ADD COLUMN is_guest TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_id");
    }
} catch (Throwable) {}

// Params
$status     = trim((string) ($_GET['status']       ?? ''));
$search     = trim((string) ($_GET['search']       ?? ''));
$governorate= trim((string) ($_GET['governorate']  ?? ''));
$city       = trim((string) ($_GET['city']         ?? ''));
$dateFrom   = trim((string) ($_GET['date_from']    ?? ''));
$dateTo     = trim((string) ($_GET['date_to']      ?? ''));
$page    = max(1, (int) ($_GET['page']      ?? 1));
$perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
$offset  = ($page - 1) * $perPage;

$validStatuses = ['pending','confirmed','preparing','shipping','delivered','cancelled'];

$where  = [];
$params = [];

if ($status !== '' && in_array($status, $validStatuses, true)) {
    $where[]           = 'o.status = :status';
    $params[':status'] = $status;
}

if ($search !== '') {
    $like              = '%' . $search . '%';
    $where[]           = '(o.order_number LIKE :s1 OR o.customer_name LIKE :s2 OR o.customer_phone LIKE :s3)';
    $params[':s1']     = $like;
    $params[':s2']     = $like;
    $params[':s3']     = $like;
}

if ($governorate !== '') {
    $where[]                = '(o.governorate LIKE :gov OR o.full_address LIKE :gov2)';
    $params[':gov']         = '%' . $governorate . '%';
    $params[':gov2']        = '%' . $governorate . '%';
}

if ($city !== '') {
    $where[]                = '(o.city LIKE :city OR o.full_address LIKE :city2)';
    $params[':city']        = '%' . $city . '%';
    $params[':city2']       = '%' . $city . '%';
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[]                = 'DATE(o.created_at) >= :date_from';
    $params[':date_from']   = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[]                = 'DATE(o.created_at) <= :date_to';
    $params[':date_to']     = $dateTo;
}

$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_orders o $whereClause");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// Orders
$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.customer_id,
        COALESCE(o.is_guest, 0)     AS is_guest,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.governorate,
        o.city,
        o.address_detail,
        o.full_address,
        o.note,
        o.status,
        o.items_json,
        o.subtotal,
        o.shipping,
        o.wallet_discount,
        o.total,
        o.created_at,
        o.updated_at
    FROM customer_orders o
    $whereClause
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();

$orders = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $items = json_decode((string)($row['items_json'] ?? '[]'), true);
    if (!is_array($items)) $items = [];

    $address = trim((string)($row['full_address'] ?? ''));
    if ($address === '') {
        $parts = array_filter([
            trim((string)($row['governorate'] ?? '')),
            trim((string)($row['city'] ?? '')),
            trim((string)($row['address_detail'] ?? '')),
        ]);
        $address = implode('، ', $parts);
    }

    $orders[] = [
        'id'             => (int)$row['id'],
        'order_number'   => (string)$row['order_number'],
        'is_guest'       => (bool)(int)$row['is_guest'],
        'customer_id'    => $row['customer_id'] ? (int)$row['customer_id'] : null,
        'customer_name'  => (string)$row['customer_name'],
        'customer_phone' => (string)$row['customer_phone'],
        'address'        => $address,
        'note'           => (string)($row['note'] ?? ''),
        'status'         => (string)$row['status'],
        'items'          => $items,
        'subtotal'       => (float)$row['subtotal'],
        'shipping'       => (float)$row['shipping'],
        'discount'       => (float)$row['wallet_discount'],
        'total'          => (float)$row['total'],
        'created_at'     => (string)$row['created_at'],
        'updated_at'     => (string)$row['updated_at'],
        'actions'        => [],   // filled below
    ];
}

// Fetch recorded admin actions for the returned orders
if (!empty($orders)) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    $idxById = [];
    foreach ($orders as $i => $o) $idxById[$o['id']] = $i;

    try {
        $actStmt = $pdo->query("
            SELECT order_id, action, admin_name, created_at
            FROM order_admin_actions
            WHERE order_id IN ($ids)
        ");
        while ($a = $actStmt->fetch(PDO::FETCH_ASSOC)) {
            $oi = $idxById[(int)$a['order_id']] ?? null;
            if ($oi !== null) {
                $orders[$oi]['actions'][(string)$a['action']] = [
                    'admin_name' => (string)$a['admin_name'],
                    'at'         => substr((string)$a['created_at'], 0, 16),
                ];
            }
        }
    } catch (Throwable) {}
}

// Status counts
$countsByStatus = [];
$csStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM customer_orders GROUP BY status");
while ($r = $csStmt->fetch(PDO::FETCH_ASSOC)) {
    $countsByStatus[(string)$r['status']] = (int)$r['cnt'];
}

// Revenue (delivered only)
$revStmt = $pdo->query("SELECT COALESCE(SUM(total),0) FROM customer_orders WHERE status='delivered'");
$revenue = (float)$revStmt->fetchColumn();

echo json_encode([
    'ok'      => true,
    'orders'  => $orders,
    'total'   => $total,
    'page'    => $page,
    'pages'   => max(1, (int)ceil($total / $perPage)),
    'per_page'=> $perPage,
    'counts'  => $countsByStatus,
    'revenue' => $revenue,
], JSON_UNESCAPED_UNICODE);
