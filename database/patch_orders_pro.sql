-- Admin patch
-- Orders: colonnes "pro" + notes internes
-- Compatible avec le schéma V1 (statuts FR existants).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Colonnes dates (optionnelles)
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER status_updated_at,
  ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER paid_at;

-- 2) Notes internes (admin-only)
CREATE TABLE IF NOT EXISTS order_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_order_notes_order (order_id, created_at),
  KEY ix_order_notes_admin (admin_id),
  CONSTRAINT fk_order_notes_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_notes_admin
    FOREIGN KEY (admin_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

