-- Admin patch
-- Workflow produits: pending/published
-- A exécuter dans phpMyAdmin sur la DB `malishop`.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Ajout de la colonne status (pending/published)
ALTER TABLE products
  ADD COLUMN status ENUM('pending','published') NOT NULL DEFAULT 'pending',
  ADD INDEX ix_products_status (status),
  ADD INDEX ix_products_created (created_at);

-- 2) Ne pas casser le site : publier les produits existants (ils étaient déjà visibles avant)
UPDATE products SET status = 'published' WHERE status = 'pending';
