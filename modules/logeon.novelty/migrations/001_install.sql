CREATE TABLE IF NOT EXISTS `news` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `excerpt` VARCHAR(255) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `type` TINYINT(2) NOT NULL DEFAULT 0,
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `author_id` INT(11) NOT NULL,
    `date_created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `date_published` TIMESTAMP NULL DEFAULT NULL,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_news_published` (`is_published`, `type`, `date_published`),
    KEY `idx_news_pinned` (`is_pinned`, `date_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `characters`
    ADD COLUMN IF NOT EXISTS `notify_news` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_invites`;
