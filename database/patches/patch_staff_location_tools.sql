-- Staff tools: granular chat restrictions + location staff notes/flags

-- users.restrict_chat
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'restrict_chat'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `restrict_chat` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_restricted`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- users.restrict_whisper
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'restrict_whisper'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `restrict_whisper` TINYINT(1) NOT NULL DEFAULT 0 AFTER `restrict_chat`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- users.restrict_commands
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'restrict_commands'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `restrict_commands` TINYINT(1) NOT NULL DEFAULT 0 AFTER `restrict_whisper`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- location_staff_notes
CREATE TABLE IF NOT EXISTS `location_staff_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) NOT NULL,
  `author_user_id` int(11) NOT NULL,
  `author_character_id` int(11) NOT NULL,
  `note_text` text NOT NULL,
  `priority` varchar(10) NOT NULL DEFAULT 'normal',
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lsn_location` (`location_id`),
  KEY `idx_lsn_author` (`author_user_id`,`author_character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- location_staff_character_flags
CREATE TABLE IF NOT EXISTS `location_staff_character_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) NOT NULL,
  `character_id` int(11) NOT NULL,
  `flag` varchar(20) NOT NULL DEFAULT 'none',
  `note_text` varchar(255) DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_by_character_id` int(11) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lscf_location_character` (`location_id`,`character_id`),
  KEY `idx_lscf_flag` (`flag`),
  KEY `idx_lscf_character` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
