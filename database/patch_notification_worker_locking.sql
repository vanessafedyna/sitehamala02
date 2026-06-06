-- Notification worker locking (safe migration)
-- Execute in phpMyAdmin (same DB as application).

SET NAMES utf8mb4;

-- Add lock columns only if missing.
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'notification_jobs'
        AND COLUMN_NAME = 'locked_at'
    ),
    'SELECT 1',
    'ALTER TABLE notification_jobs ADD COLUMN locked_at DATETIME NULL AFTER next_retry_at'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'notification_jobs'
        AND COLUMN_NAME = 'lock_token'
    ),
    'SELECT 1',
    'ALTER TABLE notification_jobs ADD COLUMN lock_token VARCHAR(64) NULL AFTER locked_at'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure due index exists.
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'notification_jobs'
        AND INDEX_NAME = 'idx_notification_due'
    ),
    'SELECT 1',
    'CREATE INDEX idx_notification_due ON notification_jobs (status, next_retry_at)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure lock index exists.
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'notification_jobs'
        AND INDEX_NAME = 'idx_notification_lock'
    ),
    'SELECT 1',
    'CREATE INDEX idx_notification_lock ON notification_jobs (lock_token)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
