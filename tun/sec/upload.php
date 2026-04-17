<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['image']['error'] ?? -1;
    echo json_encode(['ok' => false, 'error' => 'فشل الرفع، كود الخطأ: ' . $code]);
    exit;
}

$file     = $_FILES['image'];
$mimeMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);

if (!isset($mimeMap[$mime])) {
    echo json_encode(['ok' => false, 'error' => 'نوع الملف غير مدعوم. المسموح: JPG, PNG, WebP, GIF']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'حجم الملف يتجاوز 5 ميغابايت']);
    exit;
}

$ext      = $mimeMap[$mime];
$name     = bin2hex(random_bytes(10)) . '.' . $ext;
$destDir  = __DIR__ . '/../../imgs/products/';
$destPath = $destDir . $name;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'فشل حفظ الملف على السيرفر']);
    exit;
}

echo json_encode(['ok' => true, 'path' => 'imgs/products/' . $name]);
