-- Notification Queue + status upgrade (safe migration)
-- Execute in phpMyAdmin (same DB as application).

SET NAMES utf8mb4;

-- 1) Orders status: switch to robust VARCHAR flow.
ALTER TABLE orders
  MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'nouveau';

-- Map legacy statuses to new statuses.
UPDATE orders
SET status = CASE status
  WHEN 'nouvelle' THEN 'nouveau'
  WHEN 'confirmee' THEN 'confirme'
  WHEN 'preparee' THEN 'en_preparation'
  WHEN 'livree' THEN 'livre'
  ELSE status
END;

-- 2) Queue table
CREATE TABLE IF NOT EXISTS notification_jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  type VARCHAR(80) NOT NULL,
  channel VARCHAR(16) NOT NULL DEFAULT 'email',
  recipient VARCHAR(190) NOT NULL,
  payload_json TEXT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
  last_error TEXT NULL,
  next_retry_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notification_jobs_order (order_id),
  KEY ix_notification_jobs_status (status, next_retry_at),
  KEY ix_notification_jobs_type (type),
  KEY ix_notification_jobs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notification_jobs
  ADD COLUMN IF NOT EXISTS channel VARCHAR(16) NOT NULL DEFAULT 'email' AFTER type;

-- 3) Notification logs (history)
CREATE TABLE IF NOT EXISTS notification_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NULL,
  type VARCHAR(80) NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  status VARCHAR(20) NOT NULL,
  error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notification_log_order (order_id),
  KEY ix_notification_log_type (type),
  KEY ix_notification_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
