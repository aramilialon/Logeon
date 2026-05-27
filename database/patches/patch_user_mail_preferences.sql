CREATE TABLE IF NOT EXISTS `user_mail_preferences` (
    `user_id`          INT UNSIGNED  NOT NULL,
    `newsletter_opt_in` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mail_consent_logs` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED  NOT NULL,
    `consent_type` VARCHAR(64)   NOT NULL DEFAULT '',
    `value`        TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `source`       VARCHAR(64)   NOT NULL DEFAULT '',
    `ip_address`   VARCHAR(45)   NULL DEFAULT NULL,
    `user_agent`   VARCHAR(500)  NULL DEFAULT NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
