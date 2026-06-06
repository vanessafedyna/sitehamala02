-- MaliShop V1 - Patch: contacts + newsletter
-- A exécuter dans phpMyAdmin sur la base `malishop`.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================
-- CONTACTS
-- =========================
CREATE TABLE IF NOT EXISTS contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(32) NULL,
  subject VARCHAR(80) NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_contacts_created (created_at),
  KEY ix_contacts_email (email),
  KEY ix_contacts_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- NEWSLETTER SUBSCRIBERS
-- =========================
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ux_newsletter_email (email),
  KEY ix_newsletter_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

