CREATE TABLE IF NOT EXISTS `mail_recipients` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id`       INT UNSIGNED NOT NULL,
    `user_id`          INT UNSIGNED NULL DEFAULT NULL,
    `email`            VARCHAR(255) NOT NULL DEFAULT '',
    `consent_snapshot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `status`           ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    `sent_at`          DATETIME NULL DEFAULT NULL,
    `failed_at`        DATETIME NULL DEFAULT NULL,
    `last_error`       VARCHAR(500) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
