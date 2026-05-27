CREATE TABLE IF NOT EXISTS `social_status` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `description` TINYTEXT NOT NULL,
    `icon` VARCHAR(255) NOT NULL DEFAULT 'http://via.placeholder.com/48x48',
    `shop_discount` TINYINT(3) NOT NULL DEFAULT 0,
    `unlock_home` TINYINT(1) NOT NULL DEFAULT 0,
    `quest_tier` TINYINT(2) NOT NULL DEFAULT 0,
    `min` INT(11) NOT NULL,
    `max` INT(11) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM `social_status`;
INSERT INTO `social_status` (`id`, `name`, `description`, `icon`, `shop_discount`, `unlock_home`, `quest_tier`, `min`, `max`) VALUES
    (1, 'Sconosciuto', 'Il personaggio e poco noto e non ha rilevanza sociale.', '/assets/imgs/defaults-images/default-icon.png', 0, 0, 0, 0, 19),
    (2, 'Riconosciuto', 'Il personaggio e noto nel proprio settore e inizia ad avere rilievo sociale.', '/assets/imgs/defaults-images/default-icon.png', 0, 0, 0, 20, 49),
    (3, 'Famoso', 'Il personaggio e noto per imprese e qualita e difficilmente passa inosservato.', '/assets/imgs/defaults-images/default-icon.png', 0, 0, 0, 50, 69),
    (4, 'Celebrita', 'Il nome del personaggio e noto su scala nazionale.', '/assets/imgs/defaults-images/default-icon.png', 0, 0, 0, 70, 99),
    (5, 'Leggenda Vivente', 'Figura di rilievo internazionale conosciuta per imprese e reputazione.', '/assets/imgs/defaults-images/default-icon.png', 0, 0, 0, 100, 9000);
