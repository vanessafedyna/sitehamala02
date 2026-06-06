/* Ajoute les colonnes et index nécessaires aux produits vedettes. */
-- Ajoute les colonnes "produits vedettes" sur la table `products`.

ALTER TABLE products
  ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN featured_rank INT NULL DEFAULT NULL,
  ADD INDEX ix_products_featured (is_featured, featured_rank);
