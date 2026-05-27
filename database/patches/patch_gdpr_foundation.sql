CREATE TABLE IF NOT EXISTS `user_legal_consents` (
    `user_id` INT(11) UNSIGNED NOT NULL,
    `privacy_policy_version` VARCHAR(32) NOT NULL DEFAULT '',
    `terms_of_service_version` VARCHAR(32) NOT NULL DEFAULT '',
    `accepted_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `source` VARCHAR(64) NOT NULL DEFAULT '',
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `legal_consent_logs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `consent_key` VARCHAR(64) NOT NULL DEFAULT '',
    `consent_version` VARCHAR(32) NOT NULL DEFAULT '',
    `value` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `source` VARCHAR(64) NOT NULL DEFAULT '',
    `ip_address` VARCHAR(64) NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_legal_consent_logs_user` (`user_id`),
    KEY `idx_legal_consent_logs_key` (`consent_key`),
    KEY `idx_legal_consent_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gdpr_requests` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `request_type` VARCHAR(64) NOT NULL DEFAULT '',
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `note` TEXT NULL,
    `payload_json` LONGTEXT NULL,
    `handled_by_user_id` INT(11) UNSIGNED NULL,
    `handled_at` DATETIME NULL,
    `resolution_note` TEXT NULL,
    `source` VARCHAR(64) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gdpr_requests_user_status` (`user_id`, `status`),
    KEY `idx_gdpr_requests_type` (`request_type`),
    KEY `idx_gdpr_requests_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cookie_consent_logs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NULL,
    `consent_key` VARCHAR(64) NOT NULL DEFAULT 'cookie_policy',
    `consent_version` VARCHAR(32) NOT NULL DEFAULT '',
    `value` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `preferences_json` LONGTEXT NULL,
    `source` VARCHAR(64) NOT NULL DEFAULT '',
    `ip_address` VARCHAR(64) NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cookie_consent_logs_user` (`user_id`),
    KEY `idx_cookie_consent_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
