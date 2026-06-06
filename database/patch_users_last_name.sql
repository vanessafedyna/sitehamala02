-- Ajoute users.last_name si absent (compatible schéma actuel: users.name existe).
SET @has_last_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'last_name'
);

SET @sql := IF(
  @has_last_name = 0,
  'ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT '''' AFTER name',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
