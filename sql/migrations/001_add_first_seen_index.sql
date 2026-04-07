-- Migration 001: Add index on first_seen for dashboard stats & chart queries
-- Run once: mysql -u root store < sql/migrations/001_add_first_seen_index.sql

ALTER TABLE visitors
    ADD KEY idx_visitors_first_seen (first_seen);
