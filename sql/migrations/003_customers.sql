-- Migration 003: Customers table for store users
-- mysql -u root store < sql/migrations/003_customers.sql

CREATE TABLE IF NOT EXISTS customers (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(120)  DEFAULT NULL,
    phone            VARCHAR(20)   NOT NULL,
    password_hash    VARCHAR(255)  NOT NULL,
    segment          ENUM('consumer','wholesale','corporate') NOT NULL DEFAULT 'consumer',
    governorate      VARCHAR(100)  DEFAULT NULL,
    governorate_id   INT UNSIGNED  DEFAULT NULL,
    city             VARCHAR(100)  DEFAULT NULL,
    city_id          INT UNSIGNED  DEFAULT NULL,
    address_detail   TEXT          DEFAULT NULL,
    lat              DECIMAL(10,7) DEFAULT NULL,
    lng              DECIMAL(10,7) DEFAULT NULL,
    profile_complete TINYINT(1)    NOT NULL DEFAULT 0,
    v_id             CHAR(34)      DEFAULT NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_customers_phone (phone),
    KEY idx_customers_v_id (v_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
