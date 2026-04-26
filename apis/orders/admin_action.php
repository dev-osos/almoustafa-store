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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$orderId = (int)($body['order_id'] ?? 0);
$action  = trim((string)($body['action'] ?? ''));

$validActions = ['whatsapp', 'call', 'erp', 'shipping'];
if ($orderId <= 0 || !in_array($action, $validActions, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'بيانات غير صالحة']);
    exit;
}

$adminId   = (int)($_SESSION['admin_id']       ?? 0);
$adminName = trim((string)($_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'مشرف'));

try {
    $pdo = api_pdo();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_admin_actions (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id   BIGINT UNSIGNED NOT NULL,
            action     VARCHAR(30)     NOT NULL,
            admin_id   INT UNSIGNED    NOT NULL DEFAULT 0,
            admin_name VARCHAR(120)    NOT NULL DEFAULT '',
            created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_oaa (order_id, action),
            KEY idx_oaa_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->prepare("
        INSERT INTO order_admin_actions (order_id, action, admin_id, admin_name)
        VALUES (:oid, :act, :aid, :name)
        ON DUPLICATE KEY UPDATE
            admin_id   = VALUES(admin_id),
            admin_name = VALUES(admin_name),
            created_at = CURRENT_TIMESTAMP
    ")->execute([
        ':oid'  => $orderId,
        ':act'  => $action,
        ':aid'  => $adminId,
        ':name' => $adminName,
    ]);

    echo json_encode(['ok' => true, 'admin_name' => $adminName], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
