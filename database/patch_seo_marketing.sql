-- Marketing patch
-- SEO basique (products)
-- Base: malishop

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS slug VARCHAR(140) NULL AFTER sku,
  ADD COLUMN IF NOT EXISTS seo_title VARCHAR(160) NULL AFTER description,
  ADD COLUMN IF NOT EXISTS seo_description VARCHAR(255) NULL AFTER seo_title,
  ADD COLUMN IF NOT EXISTS og_image VARCHAR(255) NULL AFTER seo_description;

-- Unique slug (NULL allowed => multiple NULL ok)
SET @__idx_products_slug := (
  SELECT 1 FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'ux_products_slug'
  LIMIT 1
);
SET @__sql_slug := IF(
  @__idx_products_slug IS NULL,
  'ALTER TABLE products ADD UNIQUE KEY ux_products_slug (slug);',
  'SELECT 1;'
);
PREPARE __stmt_slug FROM @__sql_slug;
EXECUTE __stmt_slug;
DEALLOCATE PREPARE __stmt_slug;

