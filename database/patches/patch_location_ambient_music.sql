-- Location — ambient music URL — v1
-- Aggiunge la colonna ambient_music_url alla tabella locations.

SET @db_name = DATABASE();

SET @has_ambient_music_url = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'locations'
      AND COLUMN_NAME = 'ambient_music_url'
);
SET @sql = IF(
    @has_ambient_music_url = 0,
    'ALTER TABLE `locations` ADD COLUMN `ambient_music_url` VARCHAR(2048) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
