<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/response.php';
require_once dirname(__DIR__) . '/db.php';

api_response_init();
api_require_method(['GET']);

$pdo  = api_pdo();
foreach (['min_wholesale_qty', 'min_corporate_qty'] as $col) {
    $hasColStmt = $pdo->query("SHOW COLUMNS FROM products LIKE " . $pdo->quote($col));
    if (!$hasColStmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN {$col} INT UNSIGNED NOT NULL DEFAULT 1");
    }
}
$stmt = $pdo->query(
    "SELECT id, store_name, api_name, category, badge, wight, price, discount, image_url, min_wholesale_qty, min_corporate_qty
     FROM products
     WHERE status = 'active' AND category != ''
     ORDER BY category ASC, id ASC"
);

api_ok(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
