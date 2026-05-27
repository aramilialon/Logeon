CREATE TABLE IF NOT EXISTS `climate_areas` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `weather_key` VARCHAR(32) DEFAULT NULL,
    `degrees` INT(11) DEFAULT NULL,
    `moon_phase` VARCHAR(32) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` INT(11) DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_climate_areas_code` (`code`),
    KEY `idx_climate_areas_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `climate_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `scope_type` VARCHAR(32) NOT NULL,
    `scope_id` INT(11) NOT NULL,
    `climate_zone_id` INT(11) NOT NULL,
    `priority` INT(11) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_climate_assign_scope_zone` (`scope_type`, `scope_id`, `climate_zone_id`),
    KEY `idx_climate_assign_scope` (`scope_type`, `scope_id`, `is_active`, `priority`),
    KEY `idx_climate_assign_zone` (`climate_zone_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `climate_zone_season_profiles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `climate_zone_id` INT(11) NOT NULL,
    `season_id` INT(11) NOT NULL,
    `temperature_min` DECIMAL(6,2) DEFAULT NULL,
    `temperature_max` DECIMAL(6,2) DEFAULT NULL,
    `temperature_round_mode` VARCHAR(16) NOT NULL DEFAULT 'round',
    `default_weather_type_id` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_climate_zone_season` (`climate_zone_id`, `season_id`),
    KEY `idx_czsp_active` (`is_active`, `climate_zone_id`, `season_id`),
    KEY `idx_czsp_weather_type` (`default_weather_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `climate_zone_weather_weights` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `profile_id` INT(11) NOT NULL,
    `weather_type_id` INT(11) NOT NULL,
    `weight` DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_czww_profile_weather` (`profile_id`, `weather_type_id`),
    KEY `idx_czww_profile_active` (`profile_id`, `is_active`),
    KEY `idx_czww_weather_active` (`weather_type_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `climate_zones` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(80) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_climate_zones_slug` (`slug`),
    KEY `idx_climate_zones_active_sort` (`is_active`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seasons` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(80) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at_month` TINYINT(3) UNSIGNED DEFAULT NULL,
    `starts_at_day` TINYINT(3) UNSIGNED DEFAULT NULL,
    `ends_at_month` TINYINT(3) UNSIGNED DEFAULT NULL,
    `ends_at_day` TINYINT(3) UNSIGNED DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_seasons_slug` (`slug`),
    KEY `idx_seasons_active_sort` (`is_active`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `weather_overrides` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `scope_type` VARCHAR(32) NOT NULL,
    `scope_id` INT(11) NOT NULL,
    `weather_type_id` INT(11) DEFAULT NULL,
    `temperature_override` DECIMAL(6,2) DEFAULT NULL,
    `reason` VARCHAR(500) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `starts_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_weather_overrides_scope` (`scope_type`, `scope_id`, `is_active`),
    KEY `idx_weather_overrides_time` (`starts_at`, `expires_at`, `is_active`),
    KEY `idx_weather_overrides_weather` (`weather_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `weather_types` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(80) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `visual_group` VARCHAR(32) DEFAULT NULL,
    `is_precipitation` TINYINT(1) NOT NULL DEFAULT 0,
    `is_snow` TINYINT(1) NOT NULL DEFAULT 0,
    `is_storm` TINYINT(1) NOT NULL DEFAULT 0,
    `reduces_visibility` TINYINT(1) NOT NULL DEFAULT 0,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_weather_types_slug` (`slug`),
    KEY `idx_weather_types_active_sort` (`is_active`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM `climate_areas`;
DELETE FROM `climate_assignments`;
DELETE FROM `climate_zone_season_profiles`;
DELETE FROM `climate_zone_weather_weights`;
DELETE FROM `climate_zones`;
DELETE FROM `weather_overrides`;
DELETE FROM `weather_types`;

DELETE FROM `seasons`;
INSERT INTO `seasons` (`id`, `name`, `slug`, `description`, `sort_order`, `is_active`, `starts_at_month`, `starts_at_day`, `ends_at_month`, `ends_at_day`, `date_created`, `date_updated`) VALUES
    (1, 'Primavera', 'spring', 'Stagione primaverile', 10, 1, 3, 21, 6, 20, NOW(), NOW()),
    (2, 'Estate', 'summer', 'Stagione estiva', 20, 1, 6, 21, 9, 22, NOW(), NOW()),
    (3, 'Autunno', 'autumn', 'Stagione autunnale', 30, 1, 9, 23, 12, 20, NOW(), NOW()),
    (4, 'Inverno', 'winter', 'Stagione invernale', 40, 1, 12, 21, 3, 20, NOW(), NOW());

INSERT INTO `sys_configs` (`key`, `value`, `type`, `date_created`, `date_updated`)
VALUES
    ('weather_climate_enabled', '1', 'number', NOW(), NULL),
    ('weather_season_mode', 'auto', 'string', NOW(), NULL),
    ('weather_active_season_id', '', 'number', NOW(), NULL),
    ('weather_fallback_scope_type', 'world', 'string', NOW(), NULL),
    ('weather_fallback_scope_id', '1', 'number', NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `type` = VALUES(`type`);
