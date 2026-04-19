-- Migration 004: Promotions & discounts system
CREATE TABLE IF NOT EXISTS promotions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(200)    NOT NULL,
  type            ENUM('bundle','product_discount','quantity_discount','gift_product','free_shipping') NOT NULL,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  config          JSON            NOT NULL DEFAULT ('{}'),
  applies_to      JSON            NULL COMMENT 'null=all segments, else array of segment values',
  start_date      DATE            NULL,
  end_date        DATE            NULL,
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_promotions_status (status),
  KEY idx_promotions_type   (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
