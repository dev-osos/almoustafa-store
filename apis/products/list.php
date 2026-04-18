<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/response.php';
require_once dirname(__DIR__) . '/db.php';

api_response_init();
api_require_method(['GET']);

$pdo  = api_pdo();
$stmt = $pdo->query(
    "SELECT id, store_name, api_name, category, badge, wight, price, discount, image_url
     FROM products
     WHERE status = 'active' AND category != ''
     ORDER BY category ASC, id ASC"
);

api_ok(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
