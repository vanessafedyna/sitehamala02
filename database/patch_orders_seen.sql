-- Suivi des nouvelles commandes pour la vue propriétaire
-- Ajoute un indicateur de lecture pour le propriétaire.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS owner_seen_at DATETIME NULL AFTER status;
