CREATE TABLE IF NOT EXISTS `location_music_state` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `location_id` INT UNSIGNED NOT NULL,
  `source_type` ENUM('youtube','audio_file','external_url') NOT NULL DEFAULT 'external_url',
  `source_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `youtube_video_id` VARCHAR(32) NULL DEFAULT NULL,
  `title` VARCHAR(255) NULL DEFAULT NULL,
  `started_by_character_id` INT UNSIGNED NULL DEFAULT NULL,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `force_muted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_location_music` (`location_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
