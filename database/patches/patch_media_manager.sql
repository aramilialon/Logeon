-- Media Manager — v1
-- Tabella metadata per i file caricati nel Media Manager admin

CREATE TABLE IF NOT EXISTS `media_files` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `stored_name`         VARCHAR(255)     NOT NULL COMMENT 'Nome univoco salvato su disco',
    `original_name`       VARCHAR(255)     NOT NULL COMMENT 'Nome originale del file al momento dell upload',
    `extension`           VARCHAR(20)      NOT NULL COMMENT 'Estensione senza punto, es. jpg',
    `mime_type`           VARCHAR(100)     NOT NULL COMMENT 'MIME rilevato al momento dell upload',
    `size`                INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'Dimensione in byte',
    `path`                VARCHAR(500)     NOT NULL DEFAULT '' COMMENT 'Percorso relativo all interno di uploads/media/ senza slash iniziale/finale',
    `uploaded_by_user_id` INT UNSIGNED     NULL DEFAULT NULL COMMENT 'FK users.id — NULL se perso',
    `created_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_path`    (`path`(191)),
    INDEX `idx_user`    (`uploaded_by_user_id`),
    INDEX `idx_ext`     (`extension`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
