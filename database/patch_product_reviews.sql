/* PRODUCT REVIEWS: avis par produit (separes des avis globaux `reviews`) */

CREATE TABLE IF NOT EXISTS product_reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  customer_name VARCHAR(100) NOT NULL,
  customer_city VARCHAR(100) NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NOT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_product_reviews_product_approved_created (product_id, is_approved, created_at),
  CONSTRAINT chk_product_reviews_rating CHECK (rating >= 1 AND rating <= 5),
  CONSTRAINT fk_product_reviews_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
