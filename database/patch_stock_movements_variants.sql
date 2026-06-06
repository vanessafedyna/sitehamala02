ALTER TABLE stock_movements
  ADD COLUMN IF NOT EXISTS variant_id INT UNSIGNED NULL AFTER product_id;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'stock_movements'
    AND index_name = 'ix_stock_movements_variant'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_stock_movements_variant ON stock_movements (variant_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'stock_movements'
    AND constraint_name = 'fk_stock_movements_variant'
    AND constraint_type = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk_exists = 0,
  'ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
