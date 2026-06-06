-- MaliShop V1 (DB existante) : Patch checkout (infos livraison + paiement)
-- Base: malishop
-- Exécutez dans phpMyAdmin / mysql CLI.
--
-- Objectifs:
-- - Permettre la commande invitée (user_id nullable) sans casser les FK
-- - Stocker le nom client sur la commande (même pour invité)
-- - Stocker une méthode de paiement (V1 = cod)
-- - Timestamp dédié aux changements de statut (admin workflow)

-- 0) Checkout invité: rendre `orders.user_id` nullable
-- Requis si votre table orders a une contrainte FK sur users(id) + NOT NULL (cas actuel).
--
-- Si votre contrainte FK n'a pas le nom `fk_orders_user`, remplacez-le par le bon nom
-- (voir phpMyAdmin > Structure > Contraintes / ou `SHOW CREATE TABLE orders;`).
ALTER TABLE orders DROP FOREIGN KEY fk_orders_user;
ALTER TABLE orders MODIFY user_id INT(11) NULL;
ALTER TABLE orders
  ADD CONSTRAINT fk_orders_user
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE SET NULL;

-- 1) Nom client (requis côté checkout)
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS customer_name VARCHAR(150) NULL AFTER order_number;

-- 2) Timestamp statut (recommandé)
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS status_updated_at DATETIME NULL AFTER status;

UPDATE orders
SET status_updated_at = COALESCE(updated_at, created_at)
WHERE status_updated_at IS NULL;

-- 3) Paiement (COD)
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'cod' AFTER status_updated_at;

