<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/response.php';
require_once dirname(__DIR__) . '/db.php';

api_response_init();
api_require_method(['GET']);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) api_error('id required', 400);

$pdo  = api_pdo();
foreach (['min_wholesale_qty', 'min_corporate_qty'] as $col) {
    $hasColStmt = $pdo->query("SHOW COLUMNS FROM products LIKE " . $pdo->quote($col));
    if (!$hasColStmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN {$col} INT UNSIGNED NOT NULL DEFAULT 1");
    }
}
foreach (['discount_consumer', 'discount_wholesale', 'discount_corporate'] as $col) {
    $hasColStmt = $pdo->query("SHOW COLUMNS FROM products LIKE " . $pdo->quote($col));
    if (!$hasColStmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN {$col} TINYINT UNSIGNED NOT NULL DEFAULT 0");
    }
}
foreach (['step_wholesale_qty', 'step_corporate_qty'] as $col) {
    $hasColStmt = $pdo->query("SHOW COLUMNS FROM products LIKE " . $pdo->quote($col));
    if (!$hasColStmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN {$col} INT UNSIGNED NOT NULL DEFAULT 6");
    }
}
$stmt = $pdo->prepare(
    "SELECT id, store_name, api_name, category, badge, wight, price, discount, discount_consumer, discount_wholesale, discount_corporate, image_url,
            description, benefits, nutrition, extra_info, min_wholesale_qty, min_corporate_qty, step_wholesale_qty, step_corporate_qty
     FROM products
     WHERE id = ? AND status = 'active'"
);
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) api_error('Not found', 404);

// Decode JSON fields
foreach (['benefits', 'nutrition'] as $field) {
    if (isset($product[$field]) && is_string($product[$field])) {
        $decoded = json_decode($product[$field], true);
        $product[$field] = is_array($decoded) ? $decoded : [];
    } else {
        $product[$field] = [];
    }
}

api_ok(['product' => $product]);
