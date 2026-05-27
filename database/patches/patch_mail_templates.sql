-- Mail Templates — v1
-- Template HTML per il sistema di comunicazione email core.

CREATE TABLE IF NOT EXISTS `mail_templates` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)  NOT NULL DEFAULT '',
    `description` VARCHAR(500)  NOT NULL DEFAULT '',
    `subject`     VARCHAR(500)  NOT NULL DEFAULT '',
    `body_html`   LONGTEXT      NOT NULL,
    `body_text`   TEXT          NOT NULL DEFAULT '',
    `category`    ENUM('transactional','system','announcement','newsletter') NOT NULL DEFAULT 'transactional',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_name`     (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
