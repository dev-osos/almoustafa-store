-- سجل حركات المحفظة: أي تغيير في الرصيد (إيداع أو سحب)
-- يُحدَّث عبر تطبيق PHP (مثال: تسجيل، دعوات، إتمام طلب)

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
