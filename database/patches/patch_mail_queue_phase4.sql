-- Add user_id column to mail_queue
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'mail_queue'
      AND COLUMN_NAME  = 'user_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `mail_queue` ADD COLUMN `user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `message_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add recipient_id column to mail_queue
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'mail_queue'
      AND COLUMN_NAME  = 'recipient_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `mail_queue` ADD COLUMN `recipient_id` INT UNSIGNED NULL DEFAULT NULL AFTER `message_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add bounce_status column to mail_queue
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'mail_queue'
      AND COLUMN_NAME  = 'bounce_status'
);
SET @sql = IF(@col_exists = 0,
    "ALTER TABLE `mail_queue` ADD COLUMN `bounce_status` ENUM('none','soft','hard') NOT NULL DEFAULT 'none' AFTER `sent_at`",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add bounce_reason column to mail_queue
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'mail_queue'
      AND COLUMN_NAME  = 'bounce_reason'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `mail_queue` ADD COLUMN `bounce_reason` VARCHAR(500) NULL DEFAULT NULL AFTER `bounce_status`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
