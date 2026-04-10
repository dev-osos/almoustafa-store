-- Migration 004: Invitations table for tracking invitation codes and their usage
-- mysql -u root store < sql/migrations/004_invitations.sql

CREATE TABLE IF NOT EXISTS invitations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(6)      NOT NULL,
    created_by      BIGINT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      TIMESTAMP       NULL DEFAULT NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    max_uses        INT UNSIGNED    DEFAULT NULL,
    usage_count     INT UNSIGNED    NOT NULL DEFAULT 0,
    description     VARCHAR(255)    DEFAULT NULL,
    UNIQUE KEY uk_invitations_code (code),
    KEY idx_invitations_created_by (created_by),
    KEY idx_invitations_created_at (created_at),
    KEY idx_invitations_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add invitation_code column to customers table if not exists
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS invitation_code VARCHAR(6) DEFAULT NULL,
ADD COLUMN IF NOT INDEX idx_customers_invitation_code (invitation_code);
