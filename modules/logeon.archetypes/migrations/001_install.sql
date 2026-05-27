CREATE TABLE IF NOT EXISTS `archetype_configs` (
    `id` TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
    `archetypes_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `archetype_required` TINYINT(1) NOT NULL DEFAULT 0,
    `multiple_archetypes_allowed` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `archetypes` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `lore_text` TEXT DEFAULT NULL,
    `icon` VARCHAR(512) DEFAULT NULL,
    `image` VARCHAR(512) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_selectable` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_archetypes_slug` (`slug`),
    KEY `idx_archetypes_active_selectable` (`is_active`, `is_selectable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `character_archetypes` (
    `character_id` INT(10) UNSIGNED NOT NULL,
    `archetype_id` INT(10) UNSIGNED NOT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`character_id`, `archetype_id`),
    KEY `idx_character_archetypes_archetype` (`archetype_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `archetype_configs` (`id`, `archetypes_enabled`, `archetype_required`, `multiple_archetypes_allowed`)
VALUES (1, 1, 0, 0)
ON DUPLICATE KEY UPDATE
    `archetypes_enabled` = `archetypes_enabled`;

INSERT INTO `archetypes` (`name`, `slug`, `description`, `lore_text`, `icon`, `image`, `is_active`, `is_selectable`, `sort_order`)
VALUES ('Umano', 'umano', 'Un semplice umano', NULL, NULL, NULL, 1, 1, 1)
ON DUPLICATE KEY UPDATE
    `slug` = `slug`;

INSERT INTO `sys_configs` (`key`, `value`, `type`, `date_created`, `date_updated`)
VALUES ('archetypes_view_mode', 'navigation', 'string', NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `value` = IF(`value` IN ('navigation', 'monolithic'), `value`, 'navigation'),
    `type` = 'string';
