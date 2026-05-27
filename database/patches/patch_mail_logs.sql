-- Mail Logs — v1
-- Storico degli invii email del sistema comunicazione core.

CREATE TABLE IF NOT EXISTS `mail_logs` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `category`   ENUM('transactional','system','announcement','newsletter') NOT NULL DEFAULT 'transactional',
    `from_email` VARCHAR(255)  NOT NULL DEFAULT '',
    `from_name`  VARCHAR(255)  NOT NULL DEFAULT '',
    `to_email`   VARCHAR(255)  NOT NULL DEFAULT '',
    `to_name`    VARCHAR(255)  NOT NULL DEFAULT '',
    `subject`    VARCHAR(500)  NOT NULL DEFAULT '',
    `status`     ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    `error`      TEXT          NULL DEFAULT NULL,
    `sent_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status`   (`status`),
    INDEX `idx_category` (`category`),
    INDEX `idx_sent_at`  (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
