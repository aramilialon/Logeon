CREATE TABLE IF NOT EXISTS `currencies` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `symbol` VARCHAR(10) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `character_wallets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `character_id` INT(11) NOT NULL,
    `currency_id` INT(11) NOT NULL,
    `balance` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `character_currency_unique` (`character_id`, `currency_id`),
    KEY `character_id` (`character_id`),
    KEY `currency_id` (`currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `currency_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `character_id` INT(11) NOT NULL,
    `currency_id` INT(11) NOT NULL,
    `account` VARCHAR(20) NOT NULL DEFAULT 'money',
    `amount` DECIMAL(11,2) NOT NULL,
    `balance_before` DECIMAL(11,2) DEFAULT NULL,
    `balance_after` DECIMAL(11,2) DEFAULT NULL,
    `source` VARCHAR(50) NOT NULL,
    `meta` TEXT DEFAULT NULL,
    `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `character_id` (`character_id`),
    KEY `currency_id` (`currency_id`),
    KEY `account` (`account`),
    KEY `source` (`source`),
    KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `currencies` (`code`, `name`, `symbol`, `image`, `is_default`, `is_active`)
SELECT
    'Coin',
    'Coin',
    'C',
    '/assets/imgs/defaults-images/default-icon.png',
    1,
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM `currencies`
    WHERE `is_default` = 1
);
