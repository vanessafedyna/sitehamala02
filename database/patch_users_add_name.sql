-- MaliShop V1 - Patch: ajouter le nom utilisateur (optionnel)
-- À exécuter dans phpMyAdmin sur la base `malishop`.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS name VARCHAR(190) NULL AFTER phone;

