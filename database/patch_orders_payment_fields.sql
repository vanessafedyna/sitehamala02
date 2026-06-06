-- MaliShop V1 - Patch commandes : champs de suivi paiement
-- A executer dans phpMyAdmin / mysql CLI.
--
-- Objectif:
-- - Preparer les commandes pour le paiement a la livraison (cod)
-- - Conserver des champs de paiement pour tracer le paiement a la livraison
-- - Ne pas modifier la logique checkout ni les champs de suivi du paiement a la livraison

SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @__col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'payment_method'
);
SET @__sql := IF(@__col_exists = 0,
  'ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT ''cod''',
  'SELECT 1'
);
PREPARE stmt FROM @__sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @__col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'payment_status'
);
SET @__sql := IF(@__col_exists = 0,
  'ALTER TABLE orders ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT ''pending''',
  'SELECT 1'
);
PREPARE stmt FROM @__sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @__col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'payment_provider'
);
SET @__sql := IF(@__col_exists = 0,
  'ALTER TABLE orders ADD COLUMN payment_provider VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @__sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @__col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'payment_reference'
);
SET @__sql := IF(@__col_exists = 0,
  'ALTER TABLE orders ADD COLUMN payment_reference VARCHAR(190) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @__sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @__col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'paid_at'
);
SET @__sql := IF(@__col_exists = 0,
  'ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt FROM @__sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
