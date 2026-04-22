<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['POST']);

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$name    = trim((string) ($body['name']    ?? ''));
$product = trim((string) ($body['product'] ?? ''));
$rating  = (int)          ($body['rating']  ?? 0);
$content = trim((string) ($body['content'] ?? ''));

if ($name === '' || $content === '' || $rating < 1 || $rating > 5) {
    api_error('بيانات غير مكتملة', 422);
}
if (mb_strlen($name) > 100) {
    api_error('الاسم طويل جداً', 422);
}
if (mb_strlen($product) > 150) {
    api_error('اسم المنتج طويل جداً', 422);
}
if (mb_strlen($content) > 2000) {
    api_error('الرأي طويل جداً', 422);
}

try {
    $pdo = api_pdo();

    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
      id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name       VARCHAR(100)     NOT NULL,
      product    VARCHAR(150)     NOT NULL DEFAULT '',
      rating     TINYINT UNSIGNED NOT NULL,
      content    TEXT             NOT NULL,
      visible    TINYINT(1)       NOT NULL DEFAULT 1,
      sort_order INT              NOT NULL DEFAULT 0,
      created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_reviews_visible (visible),
      KEY idx_reviews_sort    (sort_order),
      KEY idx_reviews_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("
        INSERT INTO reviews (name, product, rating, content, visible, sort_order)
        VALUES (:name, :product, :rating, :content, 1, 0)
    ");
    $stmt->execute([
        ':name'    => $name,
        ':product' => $product,
        ':rating'  => $rating,
        ':content' => $content,
    ]);

    api_ok(['id' => (int) $pdo->lastInsertId()]);
} catch (PDOException $e) {
    error_log('reviews/submit PDOException: ' . $e->getMessage());
    api_error('خطأ في قاعدة البيانات، يرجى المحاولة لاحقاً', 500);
} catch (Throwable $e) {
    error_log('reviews/submit error: ' . $e->getMessage());
    api_error('حدث خطأ داخلي', 500);
}
