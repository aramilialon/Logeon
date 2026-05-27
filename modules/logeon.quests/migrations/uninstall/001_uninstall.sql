DELETE FROM `sys_configs`
WHERE `key` IN ('quests_enabled', 'quests_maintenance_interval_minutes', 'quests_auto_notify');

DROP TABLE IF EXISTS `system_event_quest_links`;
DROP TABLE IF EXISTS `quest_closure_reports`;
DROP TABLE IF EXISTS `quest_progress_logs`;
DROP TABLE IF EXISTS `quest_reward_assignments`;
DROP TABLE IF EXISTS `quest_event_links`;
DROP TABLE IF EXISTS `quest_step_instances`;
DROP TABLE IF EXISTS `quest_step_definitions`;
DROP TABLE IF EXISTS `quest_conditions`;
DROP TABLE IF EXISTS `quest_outcomes`;
DROP TABLE IF EXISTS `quest_instances`;
DROP TABLE IF EXISTS `quest_definitions`;
