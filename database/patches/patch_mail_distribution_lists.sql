-- Mail Distribution Lists — v1
-- Liste di distribuzione per il sistema di comunicazione email core.

CREATE TABLE IF NOT EXISTS `mail_distribution_lists` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255) NOT NULL DEFAULT '',
    `description`   VARCHAR(500) NOT NULL DEFAULT '',
    `type`          ENUM('manual','dynamic') NOT NULL DEFAULT 'manual',
    `segment_key`   VARCHAR(100) NULL DEFAULT NULL,
    `owner_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mail_distribution_list_members` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `list_id`      INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NULL DEFAULT NULL,
    `email`        VARCHAR(255) NOT NULL DEFAULT '',
    `display_name` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_list_email` (`list_id`, `email`),
    INDEX `idx_list_id` (`list_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
