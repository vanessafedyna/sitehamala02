-- Paramètres d'exploitation et de sécurité (priorité 3)
-- Settings + shipping zones + order totals (shipping/tax) + staff disable
-- Base: malishop

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Settings (key/value)
CREATE TABLE IF NOT EXISTS settings (
  key_name VARCHAR(80) NOT NULL,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Shipping zones (city/zone)
CREATE TABLE IF NOT EXISTS shipping_zones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  city VARCHAR(80) NOT NULL,
  zone VARCHAR(80) NULL,
  fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_shipping_city_active (city, is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Seed minimal settings (idempotent)
-- V1: WhatsApp API non utilisee. On conserve les anciennes cles de settings
-- pour compatibilite de dump/import, avec valeurs neutralisees.
INSERT INTO settings (key_name, value) VALUES
  ('shop_name', 'SORA Collection'),
  ('shop_email', 'support@soracollectionmali.com'),
  ('notify_admin_email', 'admin@malishop.com'),
  ('shop_whatsapp_number', '+22392828271'),
  ('whatsapp_enabled', '0'),
  ('whatsapp_provider', ''),
  ('whatsapp_access_token', ''),
  ('whatsapp_phone_number_id', ''),
  ('whatsapp_business_account_id', ''),
  ('whatsapp_webhook_verify_token', ''),
  ('admin_whatsapp_number', ''),
  ('whatsapp_template_order_created', ''),
  ('whatsapp_template_status_update', ''),
  ('whatsapp_template_admin_new_order', ''),
  ('free_shipping_threshold', '0'),
  ('tax_rate_percent', '0'),
  ('maintenance_mode', '0'),
  ('maintenance_message', 'Maintenance en cours. Merci de revenir plus tard.'),
  ('smtp_host', ''),
  ('smtp_port', '587'),
  ('smtp_user', ''),
  ('smtp_pass', ''),
  ('smtp_from_email', ''),
  ('smtp_from_name', '')
ON DUPLICATE KEY UPDATE value = value;

-- 4) Orders: store breakdown amounts (best-effort)
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS customer_email VARCHAR(190) NULL AFTER customer_phone,
  ADD COLUMN IF NOT EXISTS shipping_fee_amount INT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_amount,
  ADD COLUMN IF NOT EXISTS tax_amount INT UNSIGNED NOT NULL DEFAULT 0 AFTER shipping_fee_amount;

-- 5) Staff: disable partner/admin accounts (in users table)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;

