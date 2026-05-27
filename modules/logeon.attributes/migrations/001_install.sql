CREATE TABLE IF NOT EXISTS `character_attribute_definitions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(80) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `attribute_group` ENUM('primary', 'secondary', 'narrative') NOT NULL DEFAULT 'primary',
    `value_type` ENUM('number') NOT NULL DEFAULT 'number',
    `position` INT(11) NOT NULL DEFAULT 0,
    `min_value` DECIMAL(12,2) DEFAULT NULL,
    `max_value` DECIMAL(12,2) DEFAULT NULL,
    `default_value` DECIMAL(12,2) DEFAULT NULL,
    `fallback_value` DECIMAL(12,2) DEFAULT NULL,
    `round_mode` ENUM('none', 'floor', 'ceil', 'round') NOT NULL DEFAULT 'none',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_derived` TINYINT(1) NOT NULL DEFAULT 0,
    `allow_manual_override` TINYINT(1) NOT NULL DEFAULT 0,
    `visible_in_profile` TINYINT(1) NOT NULL DEFAULT 1,
    `visible_in_location` TINYINT(1) NOT NULL DEFAULT 0,
    `maps_to_core_health_max` TINYINT(1) NOT NULL DEFAULT 0,
    `maps_health_active` TINYINT(4) GENERATED ALWAYS AS (
        CASE
            WHEN `is_active` = 1 AND `maps_to_core_health_max` = 1 THEN 1
            ELSE NULL
        END
    ) VIRTUAL,
    `created_by` INT(11) DEFAULT NULL,
    `updated_by` INT(11) DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_character_attribute_slug` (`slug`),
    UNIQUE KEY `uq_character_attribute_health_active` (`maps_health_active`),
    KEY `idx_character_attribute_group_position` (`attribute_group`, `position`),
    KEY `idx_character_attribute_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `character_attribute_rule_steps` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `rule_id` INT(11) NOT NULL,
    `step_order` INT(11) NOT NULL DEFAULT 1,
    `operator_code` ENUM('set', 'add', 'sub', 'mul', 'div', 'min', 'max') NOT NULL DEFAULT 'set',
    `operand_type` ENUM('attribute', 'value') NOT NULL DEFAULT 'value',
    `operand_attribute_id` INT(11) DEFAULT NULL,
    `operand_value` DECIMAL(12,2) DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_character_attribute_rule_step` (`rule_id`, `step_order`),
    KEY `idx_character_attribute_rule_steps_rule` (`rule_id`),
    KEY `idx_character_attribute_rule_steps_operand_attr` (`operand_attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `character_attribute_rules` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `attribute_id` INT(11) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `fallback_value` DECIMAL(12,2) DEFAULT NULL,
    `round_mode` ENUM('none', 'floor', 'ceil', 'round') DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_character_attribute_rule_attribute` (`attribute_id`),
    KEY `idx_character_attribute_rule_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `character_attribute_values` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `character_id` INT(11) NOT NULL,
    `attribute_id` INT(11) NOT NULL,
    `base_value` DECIMAL(12,2) DEFAULT NULL,
    `override_value` DECIMAL(12,2) DEFAULT NULL,
    `effective_value` DECIMAL(12,2) DEFAULT NULL,
    `value_source` ENUM('base', 'default', 'override', 'derived', 'fallback') NOT NULL DEFAULT 'base',
    `last_recomputed_at` TIMESTAMP NULL DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_character_attribute_value` (`character_id`, `attribute_id`),
    KEY `idx_character_attribute_values_character` (`character_id`),
    KEY `idx_character_attribute_values_attribute` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sys_configs` (`key`, `value`, `type`, `date_created`, `date_updated`)
VALUES ('character_attributes_enabled', '0', 'number', NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `type` = VALUES(`type`);
