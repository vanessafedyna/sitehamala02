-- Notifications propriétaire pour les nouvelles commandes
-- Ajoute des clés de configuration pour notifier le propriétaire lorsqu'une nouvelle commande est créée.
-- Dépend de la table `settings` (voir patch_ops_settings.sql).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Settings (idempotent)
INSERT INTO settings (key_name, value) VALUES
  ('owner_order_notify_enabled', '0'),
  ('owner_order_notify_email', '')
ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();
