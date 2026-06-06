-- Soft delete users: ajoute deleted_at si absent.
SET @has_deleted_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'deleted_at'
);

SET @sql := IF(
  @has_deleted_at = 0,
  'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
