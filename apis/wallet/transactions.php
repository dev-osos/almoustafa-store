<?php
declare(strict_types=1);

/**
 * List wallet ledger entries (credits and debits) for the signed-in customer.
 * Source: wallet_transactions
 */

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$customerId = (int) ($_SESSION['customer_id'] ?? 0);
if ($customerId === 0) {
    api_error('غير مصادق', 401);
}

$page = (int) ($_GET['page'] ?? 1);
$perPage = (int) ($_GET['per_page'] ?? 5);
if ($page < 1) {
    $page = 1;
}
if ($perPage < 1) {
    $perPage = 5;
}
if ($perPage > 25) {
    $perPage = 25;
}
$offset = ($page - 1) * $perPage;

function transactionReasonLabelAr(string $reason, ?int $refId): string
{
    return match ($reason) {
        'welcome_bonus' => 'مكافأة الترحيب',
        'referral_bonus' => 'مكافأة دعوة ناجحة',
        'order_payment' => 'خصم عند إتمام طلب',
        default => 'حركة رصيد',
    };
}

try {
    $pdo = api_pdo();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_transactions (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            amount      DECIMAL(12,2)   NOT NULL,
            type        ENUM('credit','debit') NOT NULL,
            reason      VARCHAR(100)    NOT NULL,
            ref_id      BIGINT UNSIGNED DEFAULT NULL,
            created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_wt_customer (customer_id),
            KEY idx_wt_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE customer_id = :cid");
    $countStmt->execute([':cid' => $customerId]);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = (int) max(1, (int) ceil($total / $perPage));

    $listStmt = $pdo->prepare("
        SELECT
            id,
            amount,
            type,
            reason,
            ref_id,
            created_at
        FROM wallet_transactions
        WHERE customer_id = :cid
        ORDER BY created_at DESC, id DESC
        LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
    );
    $listStmt->execute([':cid' => $customerId]);
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $row) {
        $refId = isset($row['ref_id']) && $row['ref_id'] !== null
            ? (int) $row['ref_id']
            : null;
        $reason = (string) ($row['reason'] ?? '');
        $out[] = [
            'id'         => (int) $row['id'],
            'amount'     => (float) $row['amount'],
            'type'       => (string) $row['type'],
            'reason'     => $reason,
            'label'      => transactionReasonLabelAr($reason, $refId),
            'ref_id'     => $refId,
            'created_at' => (string) $row['created_at'],
        ];
    }
} catch (Throwable) {
    api_error('تعذر تحميل سجل المعاملات', 500);
}

api_ok([
    'transactions' => $out,
    'page'         => $page,
    'per_page'     => $perPage,
    'total'        => $total,
    'total_pages'  => $totalPages,
]);
