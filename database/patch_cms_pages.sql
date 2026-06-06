-- Marketing patch
-- CMS pages simples (DB-driven)
-- Base: malishop

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  key_name VARCHAR(60) NOT NULL,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  content LONGTEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  seo_title VARCHAR(160) NULL,
  seo_description TEXT NULL,
  og_image VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_pages_key (key_name),
  UNIQUE KEY ux_pages_slug (slug),
  KEY ix_pages_published (is_published, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

