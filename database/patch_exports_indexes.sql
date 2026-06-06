-- Admin patch
-- Index utiles pour KPI/exports (si manquants)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Orders
SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'ix_orders_created_at'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_orders_created_at ON orders (created_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'ix_orders_status_created'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_orders_status_created ON orders (status, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Order items
SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'order_items'
    AND INDEX_NAME = 'ix_order_items_order_created'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_order_items_order_created ON order_items (order_id, id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
