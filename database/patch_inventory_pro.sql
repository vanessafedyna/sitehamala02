-- Admin patch
-- Inventory: seuil stock faible + mouvements enrichis

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Seuil stock faible par produit
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS low_stock_threshold INT NOT NULL DEFAULT 10 AFTER stock;

-- Index (si absent)
SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'ix_products_low_stock'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_products_low_stock ON products (low_stock_threshold)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Enrichir stock_movements (sans casser l'existant)
ALTER TABLE stock_movements
  ADD COLUMN IF NOT EXISTS change_qty INT NULL AFTER qty,
  ADD COLUMN IF NOT EXISTS reason ENUM('manual_adjust','order','restock','correction') NULL AFTER type,
  ADD COLUMN IF NOT EXISTS related_order_id INT UNSIGNED NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS admin_id INT UNSIGNED NULL AFTER related_order_id,
  ADD COLUMN IF NOT EXISTS ip VARCHAR(45) NULL AFTER admin_id;

-- Index reason (si absent)
SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'stock_movements'
    AND INDEX_NAME = 'ix_stock_movements_reason'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_stock_movements_reason ON stock_movements (reason)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index related_order_id (si absent)
SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'stock_movements'
    AND INDEX_NAME = 'ix_stock_movements_related_order'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_stock_movements_related_order ON stock_movements (related_order_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FK optionnelle pour related_order_id (à activer manuellement si besoin)
-- ALTER TABLE stock_movements
--   ADD CONSTRAINT fk_stock_movements_order
--     FOREIGN KEY (related_order_id) REFERENCES orders(id)
--     ON DELETE SET NULL ON UPDATE CASCADE;
