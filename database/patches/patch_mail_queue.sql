CREATE TABLE IF NOT EXISTS `mail_queue` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `message_id`       INT UNSIGNED  NOT NULL,
    `recipient_id`     INT UNSIGNED  NULL DEFAULT NULL,
    `recipient_email`  VARCHAR(255)  NOT NULL DEFAULT '',
    `recipient_name`   VARCHAR(255)  NOT NULL DEFAULT '',
    `status`           ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    `attempts`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `available_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_at`        DATETIME      NULL DEFAULT NULL,
    `locked_by`        VARCHAR(64)   NULL DEFAULT NULL,
    `last_error`       TEXT          NULL DEFAULT NULL,
    `sent_at`          DATETIME      NULL DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status_available` (`status`, `available_at`),
    INDEX `idx_message_id`       (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
