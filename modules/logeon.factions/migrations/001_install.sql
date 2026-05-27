CREATE TABLE IF NOT EXISTS `factions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(80) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `type` VARCHAR(60) NOT NULL DEFAULT 'political' COMMENT 'political|military|religious|criminal|mercantile|other',
    `scope` ENUM('local', 'regional', 'global') NOT NULL DEFAULT 'regional',
    `alignment` VARCHAR(60) DEFAULT NULL COMMENT 'narrative alignment e.g. lawful_good, neutral, chaotic_evil',
    `power_level` TINYINT(4) NOT NULL DEFAULT 1 COMMENT '1-10 narrative weight',
    `is_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'visible to players',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `allow_join_requests` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se 1, i player possono inviare richieste di adesione',
    `color_hex` VARCHAR(7) DEFAULT NULL,
    `icon` VARCHAR(255) DEFAULT NULL,
    `meta_json` LONGTEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_factions_code` (`code`),
    KEY `idx_factions_active` (`is_active`, `is_public`),
    KEY `idx_factions_type` (`type`),
    KEY `idx_factions_scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faction_memberships` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `faction_id` INT(11) NOT NULL,
    `character_id` INT(11) NOT NULL,
    `role` VARCHAR(60) NOT NULL DEFAULT 'member' COMMENT 'member|leader|advisor|agent|initiate',
    `rank` VARCHAR(60) DEFAULT NULL COMMENT 'narrative rank title within faction',
    `status` ENUM('active', 'inactive', 'expelled') NOT NULL DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `left_at` TIMESTAMP NULL DEFAULT NULL,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_faction_member` (`faction_id`, `character_id`),
    KEY `idx_faction_memberships_faction` (`faction_id`, `status`),
    KEY `idx_faction_memberships_character` (`character_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faction_relationships` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `faction_id` INT(11) NOT NULL,
    `target_faction_id` INT(11) NOT NULL,
    `relation_type` ENUM('ally', 'neutral', 'rival', 'enemy', 'vassal', 'overlord') NOT NULL DEFAULT 'neutral',
    `intensity` TINYINT(4) NOT NULL DEFAULT 5 COMMENT '1-10 relationship strength',
    `notes` TEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_faction_relation` (`faction_id`, `target_faction_id`),
    KEY `idx_faction_relations_faction` (`faction_id`),
    KEY `idx_faction_relations_target` (`target_faction_id`),
    KEY `idx_faction_relations_type` (`relation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faction_join_requests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `faction_id` INT(11) NOT NULL,
    `character_id` INT(11) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
    `reviewed_by_character_id` INT(11) DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fjr_faction` (`faction_id`, `status`),
    KEY `idx_fjr_character` (`character_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM `factions`;
DELETE FROM `faction_memberships`;
DELETE FROM `faction_relationships`;
DELETE FROM `faction_join_requests`;
