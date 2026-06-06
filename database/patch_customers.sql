-- Admin patch
-- Customers (fiche client) + blacklist + lien vers orders
-- NB: orders.customer_id existe déjà (lien vers users). On ajoute un nouveau lien "customer_profile_id".

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(190) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  email VARCHAR(190) NULL,
  city VARCHAR(64) NOT NULL,
  district VARCHAR(128) NULL,
  address_note VARCHAR(255) NULL,
  is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_customers_phone (phone),
  KEY ix_customers_blacklisted (is_blacklisted),
  KEY ix_customers_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS customer_profile_id INT UNSIGNED NULL AFTER customer_id;

-- Index (si absent)
SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'ix_orders_customer_profile'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX ix_orders_customer_profile ON orders (customer_profile_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FK (optionnel): activer seulement si vous êtes sûr que votre MySQL/MariaDB supporte "IF NOT EXISTS" sur contraintes.
-- Sinon, créez la contrainte manuellement.
-- ALTER TABLE orders
--   ADD CONSTRAINT fk_orders_customer_profile
--     FOREIGN KEY (customer_profile_id) REFERENCES customers(id)
--     ON DELETE SET NULL ON UPDATE CASCADE;
