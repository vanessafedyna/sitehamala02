-- MaliShop V1 - Patch: inscription/login via telephone (email optionnel)
-- A exécuter dans phpMyAdmin sur la base `malishop`.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Ajouter colonne phone (unique) pour identifier les clients
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS phone VARCHAR(32) NULL AFTER email;

-- 2) Rendre email optionnel (NULL)
-- (MySQL/MariaDB permet plusieurs NULL avec un index UNIQUE)
ALTER TABLE users
  MODIFY email VARCHAR(190) NULL;

-- 3) Index / contraintes
ALTER TABLE users
  ADD UNIQUE KEY IF NOT EXISTS ux_users_phone (phone);

