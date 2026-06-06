-- MaliShop V1 - Schéma minimal (MySQL / MariaDB)
-- À exécuter dans phpMyAdmin sur la base définie dans `config.php` (DB_NAME).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================
-- A) USERS
-- =========================
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NULL,
  phone VARCHAR(32) NULL,
  name VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','partner','customer') NOT NULL DEFAULT 'customer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_users_email (email),
  UNIQUE KEY ux_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- B) PRODUCTS
-- =========================
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  sku VARCHAR(64) NOT NULL,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  description TEXT NULL,
  category VARCHAR(64) NULL,
  stock INT NOT NULL DEFAULT 0,
  image_main VARCHAR(255) NULL,
  /* Champs de mise en avant des produits. */
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  featured_rank INT NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_products_sku (sku),
  KEY ix_products_category (category),
  KEY ix_products_is_active (is_active),
  /* Index utilisé pour les listes de produits vedettes. */
  KEY ix_products_featured (is_featured, featured_rank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- C) STOCK MOVEMENTS
-- =========================
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  type ENUM('add','remove','adjust') NOT NULL,
  qty INT NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_stock_movements_product (product_id),
  KEY ix_stock_movements_user (user_id),
  KEY ix_stock_movements_created (created_at),
  CONSTRAINT fk_stock_movements_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_stock_movements_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- D) ORDERS
-- =========================
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(32) NOT NULL,
  customer_id INT UNSIGNED NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(32) NOT NULL,
  city VARCHAR(64) NOT NULL,
  district VARCHAR(128) NOT NULL,
  landmark VARCHAR(255) NULL,
  status ENUM('nouvelle','confirmee','preparee','en_livraison','livree','annulee') NOT NULL DEFAULT 'nouvelle',
  total_amount INT UNSIGNED NOT NULL DEFAULT 0,
  otp_code VARCHAR(20) NULL,
  otp_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_orders_order_number (order_number),
  KEY ix_orders_customer (customer_id),
  KEY ix_orders_status (status),
  KEY ix_orders_phone (customer_phone),
  KEY ix_orders_created (created_at),
  CONSTRAINT fk_orders_customer
    FOREIGN KEY (customer_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- E) ORDER ITEMS
-- =========================
CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  sku_snapshot VARCHAR(64) NOT NULL,
  product_name_snapshot VARCHAR(255) NOT NULL,
  unit_price_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 1,
  line_total INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_order_items_order (order_id),
  KEY ix_order_items_product (product_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
