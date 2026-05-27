CREATE TABLE IF NOT EXISTS `quest_closure_reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_instance_id` INT(11) NOT NULL,
    `closure_type` ENUM('success','partial_success','failure','cancelled','unresolved') NOT NULL DEFAULT 'success',
    `summary_public` TEXT DEFAULT NULL,
    `summary_private` LONGTEXT DEFAULT NULL,
    `outcome_label` VARCHAR(120) NOT NULL DEFAULT 'Obiettivo completato',
    `closed_by` INT(11) DEFAULT NULL,
    `closed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `player_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `staff_notes` LONGTEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quest_closure_reports_instance` (`quest_instance_id`),
    KEY `idx_quest_closure_reports_closed_at` (`closed_at`),
    KEY `idx_quest_closure_reports_player_visible` (`player_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_conditions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_definition_id` INT(11) DEFAULT NULL,
    `quest_step_definition_id` INT(11) DEFAULT NULL,
    `condition_type` VARCHAR(80) NOT NULL,
    `operator` VARCHAR(20) NOT NULL DEFAULT 'eq',
    `condition_payload` LONGTEXT DEFAULT NULL,
    `evaluation_mode` ENUM('all_required','any_required','blocking','optional') NOT NULL DEFAULT 'all_required',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quest_conditions_definition` (`quest_definition_id`),
    KEY `idx_quest_conditions_step` (`quest_step_definition_id`),
    KEY `idx_quest_conditions_type` (`condition_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_definitions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(120) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `summary` TEXT DEFAULT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `quest_type` VARCHAR(80) NOT NULL DEFAULT 'personal',
    `intensity_level` VARCHAR(20) NOT NULL DEFAULT 'STANDARD',
    `intensity_visibility` VARCHAR(20) NOT NULL DEFAULT 'visible',
    `visibility` ENUM('public','private','staff_only','hidden') NOT NULL DEFAULT 'public',
    `scope_type` VARCHAR(40) NOT NULL DEFAULT 'character',
    `scope_id` INT(11) DEFAULT NULL,
    `availability_type` VARCHAR(40) NOT NULL DEFAULT 'automatic_unlock',
    `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `meta_json` LONGTEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `updated_by` INT(11) DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quest_definitions_slug` (`slug`),
    KEY `idx_quest_definitions_status` (`status`),
    KEY `idx_quest_definitions_scope` (`scope_type`, `scope_id`),
    KEY `idx_quest_definitions_visibility` (`visibility`),
    KEY `idx_quest_definitions_availability` (`availability_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_event_links` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_definition_id` INT(11) DEFAULT NULL,
    `quest_instance_id` INT(11) DEFAULT NULL,
    `narrative_event_id` INT(11) DEFAULT NULL,
    `system_event_id` INT(11) DEFAULT NULL,
    `link_type` VARCHAR(40) NOT NULL DEFAULT 'contextualized_by',
    `meta_json` LONGTEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quest_event_link_definition` (`quest_definition_id`),
    KEY `idx_quest_event_link_instance` (`quest_instance_id`),
    KEY `idx_quest_event_link_narrative` (`narrative_event_id`),
    KEY `idx_quest_event_link_system` (`system_event_id`),
    KEY `idx_quest_event_link_type` (`link_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_instances` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_definition_id` INT(11) NOT NULL,
    `assignee_type` VARCHAR(40) NOT NULL DEFAULT 'character',
    `assignee_id` INT(11) DEFAULT NULL,
    `current_status` ENUM('locked','available','active','completed','failed','cancelled','expired') NOT NULL DEFAULT 'available',
    `intensity_level` VARCHAR(20) DEFAULT NULL,
    `current_branch` VARCHAR(80) DEFAULT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `failed_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `source_type` VARCHAR(60) NOT NULL DEFAULT 'manual',
    `source_id` INT(11) DEFAULT NULL,
    `assigned_by` INT(11) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `last_activity_at` DATETIME DEFAULT NULL,
    `meta_json` LONGTEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quest_instances_definition_status` (`quest_definition_id`, `current_status`),
    KEY `idx_quest_instances_assignee` (`assignee_type`, `assignee_id`, `current_status`),
    KEY `idx_quest_instances_source` (`source_type`, `source_id`),
    KEY `idx_quest_instances_expire` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_outcomes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_definition_id` INT(11) NOT NULL,
    `trigger_type` VARCHAR(40) NOT NULL,
    `outcome_type` VARCHAR(80) NOT NULL,
    `outcome_payload` LONGTEXT DEFAULT NULL,
    `visibility` ENUM('public','private','staff_only','hidden') NOT NULL DEFAULT 'hidden',
    `requires_staff_confirmation` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quest_outcomes_definition` (`quest_definition_id`, `trigger_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_progress_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_instance_id` INT(11) NOT NULL,
    `step_instance_id` INT(11) DEFAULT NULL,
    `log_type` VARCHAR(60) NOT NULL,
    `source_type` VARCHAR(60) NOT NULL DEFAULT 'system',
    `source_id` INT(11) DEFAULT NULL,
    `payload` LONGTEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quest_progress_logs_instance` (`quest_instance_id`, `date_created`),
    KEY `idx_quest_progress_logs_step` (`step_instance_id`),
    KEY `idx_quest_progress_logs_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_reward_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_instance_id` INT(11) NOT NULL,
    `recipient_type` VARCHAR(40) NOT NULL DEFAULT 'character',
    `recipient_id` INT(11) DEFAULT NULL,
    `reward_type` VARCHAR(60) NOT NULL,
    `reward_reference_id` INT(11) DEFAULT NULL,
    `reward_value` DECIMAL(14,2) DEFAULT NULL,
    `assigned_by` INT(11) DEFAULT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `visibility` ENUM('public','player_private','staff_only') NOT NULL DEFAULT 'public',
    `notes` TEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quest_reward_assignments_instance` (`quest_instance_id`),
    KEY `idx_quest_reward_assignments_recipient` (`recipient_type`, `recipient_id`),
    KEY `idx_quest_reward_assignments_visibility` (`visibility`),
    KEY `idx_quest_reward_assignments_assigned_at` (`assigned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_step_definitions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_definition_id` INT(11) NOT NULL,
    `step_key` VARCHAR(120) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `step_type` VARCHAR(80) NOT NULL DEFAULT 'narrative_action',
    `order_index` INT(11) NOT NULL DEFAULT 0,
    `is_optional` TINYINT(1) NOT NULL DEFAULT 0,
    `completion_mode` VARCHAR(40) NOT NULL DEFAULT 'automatic',
    `branch_on_success` VARCHAR(80) DEFAULT NULL,
    `branch_on_failure` VARCHAR(80) DEFAULT NULL,
    `visibility_mode` VARCHAR(40) NOT NULL DEFAULT 'visible',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `meta_json` LONGTEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quest_step_definition_key` (`quest_definition_id`, `step_key`),
    KEY `idx_quest_step_definition_order` (`quest_definition_id`, `order_index`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quest_step_instances` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quest_instance_id` INT(11) NOT NULL,
    `quest_step_definition_id` INT(11) NOT NULL,
    `progress_status` ENUM('pending','active','completed','failed','skipped','locked') NOT NULL DEFAULT 'locked',
    `progress_value` DECIMAL(12,2) DEFAULT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `failed_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `internal_notes` TEXT DEFAULT NULL,
    `meta_json` LONGTEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quest_step_instance` (`quest_instance_id`, `quest_step_definition_id`),
    KEY `idx_quest_step_instance_status` (`quest_instance_id`, `progress_status`),
    KEY `idx_quest_step_instance_definition` (`quest_step_definition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_event_quest_links` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `system_event_id` INT(11) NOT NULL,
    `quest_id` INT(11) NOT NULL,
    `link_type` ENUM('primary','secondary') NOT NULL DEFAULT 'primary',
    `meta_json` LONGTEXT DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_system_event_quest_link` (`system_event_id`, `quest_id`),
    KEY `idx_system_event_quest_quest` (`quest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sys_configs` (`key`, `value`, `type`, `date_created`, `date_updated`)
VALUES
    ('quests_enabled', '1', 'number', NOW(), NULL),
    ('quests_maintenance_interval_minutes', '5', 'number', NOW(), NULL),
    ('quests_auto_notify', '1', 'number', NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `type` = VALUES(`type`);
