<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);

try {
$pdo = api_pdo();

// Auto-create table if missing
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

// Reviews: visible=1, sorted by sort_order DESC then created_at DESC
$stmt = $pdo->query("
    SELECT id, name, product, rating, content, created_at
    FROM reviews
    WHERE visible = 1
    ORDER BY sort_order DESC, created_at DESC
");
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        ROUND(AVG(rating), 1) AS avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS r5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS r4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS r3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS r2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS r1
    FROM reviews
    WHERE visible = 1
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$total = (int) $stats['total'];
$pct = function(int $n) use ($total): int {
    return $total > 0 ? (int) round($n * 100 / $total) : 0;
};

api_ok([
    'reviews' => $reviews,
    'stats'   => [
        'total'      => $total,
        'avg_rating' => $total > 0 ? (float) $stats['avg_rating'] : 0,
        'pct_5'      => $pct((int) $stats['r5']),
        'pct_4'      => $pct((int) $stats['r4']),
        'pct_3'      => $pct((int) $stats['r3']),
        'pct_2'      => $pct((int) $stats['r2']),
        'pct_1'      => $pct((int) $stats['r1']),
        'satisfaction' => $total > 0
            ? (int) round(((int)$stats['r5'] + (int)$stats['r4']) * 100 / $total)
            : 0,
    ],
]);
} catch (PDOException $e) {
    error_log('reviews/list PDOException: ' . $e->getMessage());
    api_error('خطأ في قاعدة البيانات', 500);
} catch (Throwable $e) {
    error_log('reviews/list error: ' . $e->getMessage());
    api_error('حدث خطأ داخلي', 500);
}
