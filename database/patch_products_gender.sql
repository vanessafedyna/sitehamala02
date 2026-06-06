-- Patch: ajout du genre produit pour le filtre Catalogue Homme/Femme.
-- Base: malishop

SET NAMES utf8mb4;

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS gender ENUM('homme','femme','unisex') NOT NULL DEFAULT 'unisex' AFTER category;

-- Backfill best-effort depuis la categorie legacy.
UPDATE products
SET gender = CASE
  WHEN LOWER(TRIM(COALESCE(category, ''))) IN ('robes', 'robe') THEN 'femme'
  WHEN LOWER(TRIM(COALESCE(category, ''))) IN ('chemises', 'chemise', 'pantalons', 'pantalon', 'vestes', 'veste', 'chandails', 'chandail', 't-shirts', 't-shirt', 'tshirt', 'tshirts', 'boubous', 'ensemble', 'ensembles') THEN 'homme'
  ELSE 'unisex'
END
WHERE COALESCE(TRIM(gender), '') = '' OR gender = 'unisex';

SET @__idx_products_gender := (
  SELECT 1 FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'ix_products_gender'
  LIMIT 1
);
SET @__sql_gender_idx := IF(
  @__idx_products_gender IS NULL,
  'CREATE INDEX ix_products_gender ON products (gender);',
  'SELECT 1;'
);
PREPARE __stmt_gender_idx FROM @__sql_gender_idx;
EXECUTE __stmt_gender_idx;
DEALLOCATE PREPARE __stmt_gender_idx;
