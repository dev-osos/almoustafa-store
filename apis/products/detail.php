<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/response.php';
require_once dirname(__DIR__) . '/db.php';

api_response_init();
api_require_method(['GET']);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) api_error('id required', 400);

$pdo  = api_pdo();
$stmt = $pdo->prepare(
    "SELECT id, store_name, api_name, category, badge, wight, price, discount, image_url,
            description, benefits, nutrition, extra_info
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
