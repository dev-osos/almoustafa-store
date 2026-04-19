<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/response.php';
require_once dirname(__DIR__) . '/db.php';

api_response_init();
api_require_method(['GET']);

$pdo = api_pdo();

// Fetch active promotions (within date range if set)
$today = date('Y-m-d');
$stmt  = $pdo->prepare(
    "SELECT id, name, type, config, applies_to, start_date, end_date
     FROM promotions
     WHERE status = 'active'
       AND (start_date IS NULL OR start_date <= :today1)
       AND (end_date   IS NULL OR end_date   >= :today2)
     ORDER BY id DESC"
);
$stmt->execute([':today1' => $today, ':today2' => $today]);
$promos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($promos)) {
    api_ok(['promotions' => []]);
}

// Collect all referenced product IDs
$allIds = [];
foreach ($promos as &$pr) {
    $pr['config']     = json_decode($pr['config']     ?? '{}',   true) ?: [];
    $pr['applies_to'] = json_decode($pr['applies_to'] ?? 'null', true);

    $cfg = $pr['config'];
    if (!empty($cfg['product_ids']))    foreach ($cfg['product_ids']    as $id) $allIds[$id] = true;
    if (!empty($cfg['product_id']))     $allIds[(int)$cfg['product_id']] = true;
    if (!empty($cfg['gift_product_id'])) $allIds[(int)$cfg['gift_product_id']] = true;
}
unset($pr);

// Fetch all referenced products in one query
$productMap = [];
if ($allIds) {
    $placeholders = implode(',', array_fill(0, count($allIds), '?'));
    $pStmt = $pdo->prepare(
        "SELECT id, store_name, price, discount, image_url, wight, badge
         FROM products WHERE id IN ($placeholders) AND status = 'active'"
    );
    $pStmt->execute(array_keys($allIds));
    foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $productMap[(int)$p['id']] = $p;
    }
}

// Populate product details into each promo
function resolveProducts(array $ids, array $map): array {
    return array_values(array_filter(array_map(fn($id) => $map[(int)$id] ?? null, $ids)));
}

foreach ($promos as &$pr) {
    $cfg = &$pr['config'];
    if (!empty($cfg['product_ids']))
        $cfg['products'] = resolveProducts($cfg['product_ids'], $productMap);
    if (!empty($cfg['product_id']))
        $cfg['product'] = $productMap[(int)$cfg['product_id']] ?? null;
    if (!empty($cfg['gift_product_id']))
        $cfg['gift_product'] = $productMap[(int)$cfg['gift_product_id']] ?? null;
}
unset($pr, $cfg);

api_ok(['promotions' => $promos]);
