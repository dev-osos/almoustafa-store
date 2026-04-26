<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/response.php';
require_once dirname(__DIR__) . '/db.php';

api_response_init();

// ── Simple admin key auth ──────────────────────────────────────────────────
$adminKey = api_env('ADMIN_KEY', '');
$provided = $_SERVER['HTTP_X_ADMIN_KEY'] ?? ($_GET['admin_key'] ?? '');
if ($adminKey === '' || $provided !== $adminKey) {
    api_error('Unauthorized', 401);
}

$method = api_require_method(['GET', 'POST', 'DELETE']);
$pdo    = api_pdo();

// Ensure min-qty columns exist for segment-based cart defaults.
foreach (['min_wholesale_qty', 'min_corporate_qty'] as $col) {
    $hasColStmt = $pdo->query("SHOW COLUMNS FROM products LIKE " . $pdo->quote($col));
    if (!$hasColStmt->fetch()) {
        $defaultVal = $col === 'min_corporate_qty' ? 60 : 6;
        $pdo->exec("ALTER TABLE products ADD COLUMN {$col} INT UNSIGNED NOT NULL DEFAULT {$defaultVal}");
    }
}
foreach (['discount_consumer', 'discount_wholesale', 'discount_corporate'] as $col) {
    $hasColStmt = $pdo->query("SHOW COLUMNS FROM products LIKE " . $pdo->quote($col));
    if (!$hasColStmt->fetch()) {
        $defaultVal = $col === 'discount_wholesale' ? 20 : ($col === 'discount_corporate' ? 30 : 0);
        $pdo->exec("ALTER TABLE products ADD COLUMN {$col} TINYINT UNSIGNED NOT NULL DEFAULT {$defaultVal}");
    }
}

// ── GET: list all products ─────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT id, erp_id, api_name, store_name, category, status, badge, wight, price, discount, discount_consumer, discount_wholesale, discount_corporate, sold_q, image_url, source, description, benefits, nutrition, extra_info, min_wholesale_qty, min_corporate_qty
         FROM products
         ORDER BY id ASC'
    );
    api_ok(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── POST: create / update ──────────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    $fields = ['erp_id','api_name','store_name','category','status','badge','wight','price','discount_consumer','discount_wholesale','discount_corporate','sold_q','image_url','source','description','benefits','nutrition','extra_info','min_wholesale_qty','min_corporate_qty'];

    // Encode JSON fields
    foreach (['benefits', 'nutrition'] as $jf) {
        if (isset($body[$jf]) && is_array($body[$jf])) {
            $body[$jf] = json_encode($body[$jf], JSON_UNESCAPED_UNICODE);
        }
    }
    $body['min_wholesale_qty'] = max(1, (int) ($body['min_wholesale_qty'] ?? 6));
    $body['min_corporate_qty'] = max(1, (int) ($body['min_corporate_qty'] ?? 60));
    $body['discount_consumer'] = max(0, min(99, (int) ($body['discount_consumer'] ?? $body['discount'] ?? 0)));
    $body['discount_wholesale'] = max(0, min(99, (int) ($body['discount_wholesale'] ?? 20)));
    $body['discount_corporate'] = max(0, min(99, (int) ($body['discount_corporate'] ?? 30)));

    if ($action === 'create') {
        $cols = implode(', ', $fields);
        $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));
        $stmt = $pdo->prepare("INSERT INTO products ($cols) VALUES ($placeholders)");
        foreach ($fields as $f) {
            $stmt->bindValue(":$f", $body[$f] ?? null);
        }
        $stmt->execute();
        $newId = (int) $pdo->lastInsertId();
        $row = $pdo->prepare('SELECT id, erp_id, api_name, store_name, category, status, badge, wight, price, discount, discount_consumer, discount_wholesale, discount_corporate, sold_q, image_url, source, description, benefits, nutrition, extra_info, min_wholesale_qty, min_corporate_qty FROM products WHERE id = ?');
        $row->execute([$newId]);
        api_ok(['product' => $row->fetch(PDO::FETCH_ASSOC)], 201);
    }

    if ($action === 'update') {
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) api_error('id required');
        $sets = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
        $stmt = $pdo->prepare("UPDATE products SET $sets WHERE id = :id");
        foreach ($fields as $f) {
            $stmt->bindValue(":$f", $body[$f] ?? null);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        api_ok(['updated' => $stmt->rowCount()]);
    }

    api_error('Unknown action');
}

// ── DELETE ─────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int) ($body['id'] ?? 0);
    if ($id <= 0) api_error('id required');
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    api_ok(['deleted' => $stmt->rowCount()]);
}
