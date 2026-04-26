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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'بيانات غير صالحة']); exit; }

$orderId = (int)($body['id'] ?? 0);
$status  = trim((string)($body['status'] ?? ''));
$note    = trim((string)($body['note'] ?? ''));

$validStatuses = ['pending','confirmed','preparing','shipping','delivered','cancelled'];

if ($orderId <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'رقم الطلب مطلوب']); exit; }
if (!in_array($status, $validStatuses, true)) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'حالة غير صالحة']); exit; }

try {
    $pdo = api_pdo();

    // Ensure admin_note column exists
    try {
        $hasNote = $pdo->query("SHOW COLUMNS FROM customer_orders LIKE 'admin_note'")->fetch();
        if (!$hasNote) {
            $pdo->exec("ALTER TABLE customer_orders ADD COLUMN admin_note TEXT NULL DEFAULT NULL AFTER note");
        }
    } catch (Throwable) {}

    $fields = 'status = :status';
    $params = [':status' => $status, ':id' => $orderId];

    if ($note !== '') {
        $fields .= ', admin_note = :note';
        $params[':note'] = $note;
    }

    $stmt = $pdo->prepare("UPDATE customer_orders SET $fields WHERE id = :id");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'الطلب غير موجود']);
        exit;
    }

    echo json_encode(['ok'=>true,'status'=>$status], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'حدث خطأ','debug'=>$e->getMessage()]);
}
