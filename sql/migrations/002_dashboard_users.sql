-- Migration 002: Dashboard users table with role-based access control
-- The table is auto-created by index.php on first load.
-- Run this manually only if you prefer to set up the table in advance:
--   mysql -u root store < sql/migrations/002_dashboard_users.sql

CREATE TABLE IF NOT EXISTS dashboard_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('super_admin','admin','support') NOT NULL DEFAULT 'support',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_du_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default super admin (username: 1 / password: 1)
-- The hash below is bcrypt of '1'. index.php auto-seeds this on first load
-- if the table is empty, so this INSERT is only needed for manual setup.
-- To regenerate: php -r "echo password_hash('1', PASSWORD_BCRYPT, ['cost'=>12]);"
