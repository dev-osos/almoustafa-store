CREATE TABLE IF NOT EXISTS wallets (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  balance     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_wallets_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  v_id CHAR(34) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  first_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  hit_count INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uk_visitors_v_id (v_id),
  KEY idx_visitors_last_seen  (last_seen),
  KEY idx_visitors_first_seen (first_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100)     NOT NULL,
  product    VARCHAR(150)     NOT NULL DEFAULT '',
  rating     TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
  content    TEXT             NOT NULL,
  visible    TINYINT(1)       NOT NULL DEFAULT 1,
  sort_order INT              NOT NULL DEFAULT 0,
  created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reviews_visible (visible),
  KEY idx_reviews_sort    (sort_order),
  KEY idx_reviews_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
