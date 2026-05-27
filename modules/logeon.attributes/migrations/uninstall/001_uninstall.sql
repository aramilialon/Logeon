DELETE FROM `sys_configs`
WHERE `key` IN ('character_attributes_enabled');

DROP TABLE IF EXISTS `character_attribute_values`;
DROP TABLE IF EXISTS `character_attribute_rule_steps`;
DROP TABLE IF EXISTS `character_attribute_rules`;
DROP TABLE IF EXISTS `character_attribute_definitions`;
