-- Performance hardening for heavy admin grids (users / mail logs)

-- users: sorting/filtering support for admin list
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_date_created_id'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_date_created_id` (`date_created`, `id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_date_actived_last_signin'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_date_actived_last_signin` (`date_actived`, `date_last_signin`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_last_signout_id'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_last_signout_id` (`date_last_signout`, `id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- characters: faster first-character lookup by user
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'characters'
      AND INDEX_NAME = 'idx_characters_user_delete_id'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `characters` ADD INDEX `idx_characters_user_delete_id` (`user_id`, `delete_scheduled_at`, `id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- mail_queue: faster admin logs filtering/pagination
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mail_queue'
      AND INDEX_NAME = 'idx_mail_queue_status_id'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `mail_queue` ADD INDEX `idx_mail_queue_status_id` (`status`, `id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mail_queue'
      AND INDEX_NAME = 'idx_mail_queue_message_status_id'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `mail_queue` ADD INDEX `idx_mail_queue_message_status_id` (`message_id`, `status`, `id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
