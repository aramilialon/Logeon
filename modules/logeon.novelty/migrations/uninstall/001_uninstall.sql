DROP TABLE IF EXISTS `news`;

ALTER TABLE `characters`
    DROP COLUMN IF EXISTS `notify_news`;
