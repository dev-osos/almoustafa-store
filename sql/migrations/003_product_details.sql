-- Migration 003: Add rich content fields to products table
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS description  TEXT         NULL AFTER image_url,
  ADD COLUMN IF NOT EXISTS benefits     JSON         NULL AFTER description,
  ADD COLUMN IF NOT EXISTS nutrition    JSON         NULL AFTER benefits,
  ADD COLUMN IF NOT EXISTS extra_info   TEXT         NULL AFTER nutrition;
