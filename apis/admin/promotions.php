<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/response.php';
require_once dirname(__DIR__) . '/db.php';

api_response_init();

$adminKey = api_env('ADMIN_KEY', '');
$provided = $_SERVER['HTTP_X_ADMIN_KEY'] ?? ($_GET['admin_key'] ?? '');
if ($adminKey === '' || $provided !== $adminKey) {
    api_error('Unauthorized', 401);
}

$method = api_require_method(['GET', 'POST', 'DELETE']);
$pdo    = api_pdo();

// ── GET: list all promotions ───────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT id, name, type, status, config, applies_to, start_date, end_date, created_at
         FROM promotions ORDER BY id DESC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['config']     = json_decode($r['config'] ?? '{}', true)  ?: [];
        $r['applies_to'] = json_decode($r['applies_to'] ?? 'null', true);
    }
    api_ok(['promotions' => $rows]);
}

// ── POST: create / update ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    $name       = trim($body['name'] ?? '');
    $type       = $body['type'] ?? '';
    $status     = in_array($body['status'] ?? '', ['active','inactive']) ? $body['status'] : 'active';
    $config     = is_array($body['config'] ?? null) ? json_encode($body['config'], JSON_UNESCAPED_UNICODE) : '{}';
    $appliesTo  = isset($body['applies_to']) && is_array($body['applies_to']) && count($body['applies_to']) > 0
                    ? json_encode($body['applies_to'], JSON_UNESCAPED_UNICODE)
                    : null;
    $startDate  = !empty($body['start_date']) ? $body['start_date'] : null;
    $endDate    = !empty($body['end_date'])   ? $body['end_date']   : null;

    if (!$name) api_error('الاسم مطلوب');
    $validTypes = ['bundle','product_discount','quantity_discount','gift_product','free_shipping'];
    if (!in_array($type, $validTypes, true)) api_error('نوع العرض غير صالح');

    if ($action === 'create') {
        $stmt = $pdo->prepare(
            'INSERT INTO promotions (name, type, status, config, applies_to, start_date, end_date)
             VALUES (:name, :type, :status, :config, :applies_to, :start_date, :end_date)'
        );
        $stmt->execute([':name'=>$name,':type'=>$type,':status'=>$status,':config'=>$config,':applies_to'=>$appliesTo,':start_date'=>$startDate,':end_date'=>$endDate]);
        $newId = (int) $pdo->lastInsertId();
        $row   = $pdo->prepare('SELECT id, name, type, status, config, applies_to, start_date, end_date, created_at FROM promotions WHERE id = ?');
        $row->execute([$newId]);
        $p = $row->fetch(PDO::FETCH_ASSOC);
        $p['config']     = json_decode($p['config'] ?? '{}', true) ?: [];
        $p['applies_to'] = json_decode($p['applies_to'] ?? 'null', true);
        api_ok(['promotion' => $p], 201);
    }

    if ($action === 'update') {
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) api_error('id required');
        $stmt = $pdo->prepare(
            'UPDATE promotions SET name=:name, type=:type, status=:status, config=:config,
             applies_to=:applies_to, start_date=:start_date, end_date=:end_date WHERE id=:id'
        );
        $stmt->execute([':name'=>$name,':type'=>$type,':status'=>$status,':config'=>$config,':applies_to'=>$appliesTo,':start_date'=>$startDate,':end_date'=>$endDate,':id'=>$id]);
        api_ok(['updated' => $stmt->rowCount()]);
    }

    api_error('Unknown action');
}

// ── DELETE ─────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int) ($body['id'] ?? 0);
    if ($id <= 0) api_error('id required');
    $stmt = $pdo->prepare('DELETE FROM promotions WHERE id = ?');
    $stmt->execute([$id]);
    api_ok(['deleted' => $stmt->rowCount()]);
}
