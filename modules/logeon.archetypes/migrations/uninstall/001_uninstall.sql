DELETE FROM `sys_configs`
WHERE `key` IN ('archetypes_view_mode');

DROP TABLE IF EXISTS `character_archetypes`;
DROP TABLE IF EXISTS `archetypes`;
DROP TABLE IF EXISTS `archetype_configs`;
