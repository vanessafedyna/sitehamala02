-- Marketing patch
-- Promotions / Codes promo (DB-driven)
-- Base: malishop

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Table coupons
CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  type ENUM('percent','fixed') NOT NULL,
  value DECIMAL(10,2) NOT NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  min_subtotal DECIMAL(10,2) NULL,
  max_uses INT NULL,
  uses_count INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_coupons_code (code),
  KEY ix_coupons_active_dates (is_active, starts_at, ends_at),
  KEY ix_coupons_uses (uses_count, max_uses)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Coupon categories restriction (optional)
CREATE TABLE IF NOT EXISTS coupon_categories (
  coupon_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (coupon_id, category_id),
  KEY ix_cc_category (category_id, coupon_id),
  CONSTRAINT fk_cc_coupon
    FOREIGN KEY (coupon_id) REFERENCES coupons(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cc_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Orders: store coupon/discount/subtotal (compat: total_amount already exists)
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS coupon_id INT UNSIGNED NULL AFTER customer_id,
  ADD COLUMN IF NOT EXISTS subtotal_amount INT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN IF NOT EXISTS discount_amount INT UNSIGNED NOT NULL DEFAULT 0 AFTER subtotal_amount;

-- FK (safe): only if not already present
SET @__fk_name := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'coupon_id'
    AND REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1
);
SET @__sql_fk := IF(
  @__fk_name IS NULL,
  'ALTER TABLE orders ADD CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL ON UPDATE CASCADE;',
  'SELECT 1;'
);
PREPARE __stmt_fk FROM @__sql_fk;
EXECUTE __stmt_fk;
DEALLOCATE PREPARE __stmt_fk;

-- Useful indexes (idempotent)
SET @__idx_orders_coupon := (
  SELECT 1 FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='ix_orders_coupon'
  LIMIT 1
);
SET @__sql_idx := IF(@__idx_orders_coupon IS NULL,
  'ALTER TABLE orders ADD KEY ix_orders_coupon (coupon_id);',
  'SELECT 1;'
);
PREPARE __stmt_idx FROM @__sql_idx;
EXECUTE __stmt_idx;
DEALLOCATE PREPARE __stmt_idx;

