<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);

$phone = trim($_GET['phone'] ?? '');
if ($phone === '') {
    api_error('رقم الهاتف مطلوب', 400);
}

$phone = preg_replace('/[\s\-().]+/', '', $phone);
if (!str_starts_with($phone, '+')) {
    $phone = '+' . ltrim($phone, '0');
}

if (!preg_match('/^\+\d{7,15}$/', $phone)) {
    api_ok(['exists' => false]);
}

try {
    $pdo  = api_pdo();
    $stmt = $pdo->prepare("SELECT 1 FROM customers WHERE phone = :p LIMIT 1");
    $stmt->execute([':p' => $phone]);
    api_ok(['exists' => $stmt->fetchColumn() !== false]);
} catch (Throwable) {
    api_ok(['exists' => false]);
}
