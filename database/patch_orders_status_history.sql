-- MaliShop V1 (DB existante) : Patch workflow statuts + historique
-- Base: malishop
-- Executez ces requetes dans phpMyAdmin / mysql CLI.
--
-- Notes:
-- - Certaines colonnes peuvent deja exister selon votre schema. Dans ce cas, sautez les ALTER concernes.
-- - Le code est compatible meme sans ces ALTER (sauf l'historique, qui necessite la table order_status_history).

-- 1) (Recommande) Timestamp dedie aux changements de statut
-- Si la colonne existe deja, ignorez cette requete.
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS status_updated_at DATETIME NULL AFTER status;

UPDATE orders
SET status_updated_at = COALESCE(updated_at, created_at)
WHERE status_updated_at IS NULL;

-- 2) (Optionnel) Stocker le nom du client directement sur la commande (utile si customer_id/user_id NULL / invite)
-- Si la colonne existe deja, ignorez cette requete.
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS customer_name VARCHAR(150) NULL AFTER order_number;

-- Backfill depuis users.name si possible.
-- Compat: certaines versions ont `orders.customer_id`, d'autres `orders.user_id`.
SET @__orders_join_col := NULL;
SET @__orders_join_col := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE table_schema = DATABASE()
        AND table_name = 'orders'
        AND column_name = 'customer_id'
    ),
    'customer_id',
    NULL
  )
);
SET @__orders_join_col := (
  SELECT IF(
    @__orders_join_col IS NULL
    AND EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE table_schema = DATABASE()
        AND table_name = 'orders'
        AND column_name = 'user_id'
    ),
    'user_id',
    @__orders_join_col
  )
);

SET @__sql := IF(
  @__orders_join_col IS NULL,
  'SELECT 1;',
  CONCAT(
    'UPDATE orders o ',
    'LEFT JOIN users u ON u.id = o.', @__orders_join_col, ' ',
    'SET o.customer_name = u.name ',
    'WHERE (o.customer_name IS NULL OR o.customer_name = '''') AND u.name IS NOT NULL;'
  )
);
PREPARE __stmt FROM @__sql;
EXECUTE __stmt;
DEALLOCATE PREPARE __stmt;

-- 3) Historique des changements de statut (timeline pro)
CREATE TABLE IF NOT EXISTS order_status_history (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  note TEXT NULL,
  changed_by INT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_osh_order_id (order_id),
  KEY ix_osh_changed_by (changed_by),
  KEY ix_osh_changed_at (changed_at),
  CONSTRAINT fk_osh_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_osh_user
    FOREIGN KEY (changed_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) (Optionnel) Si la table existe deja, ajouter la colonne `note` (si absente)
ALTER TABLE order_status_history
  ADD COLUMN IF NOT EXISTS note TEXT NULL AFTER new_status;

