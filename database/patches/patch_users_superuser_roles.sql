-- Superuser roles and creator guard

-- Drop legacy unique guard index if present
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_superuser_unique_guard'
);
SET @sql = IF(@idx_exists > 0,
    'ALTER TABLE `users` DROP INDEX `uq_users_superuser_unique_guard`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop legacy generated column if present
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'superuser_unique_guard'
);
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE `users` DROP COLUMN `superuser_unique_guard`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add superuser role column if missing
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'superuser_role'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `superuser_role` VARCHAR(32) NULL DEFAULT NULL AFTER `is_superuser`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Promote the legacy single superuser to creator
UPDATE `users`
SET `superuser_role` = 'creatore'
WHERE `is_superuser` = 1
  AND (`superuser_role` IS NULL OR TRIM(`superuser_role`) = '');

-- Cleanup non-superuser labels
UPDATE `users`
SET `superuser_role` = NULL
WHERE `is_superuser` <> 1;

-- Add creator guard generated column if missing
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'superuser_creator_guard'
);
SET @sql = IF(@col_exists = 0,
    "ALTER TABLE `users` ADD COLUMN `superuser_creator_guard` TINYINT(4) GENERATED ALWAYS AS (CASE WHEN `is_superuser` = 1 AND `superuser_role` = 'creatore' THEN 1 ELSE NULL END) VIRTUAL",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unique creator guard index if missing
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_superuser_creator_guard'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `users` ADD UNIQUE KEY `uq_users_superuser_creator_guard` (`superuser_creator_guard`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
