DELETE FROM `sys_configs`
WHERE `key` IN (
    'weather_climate_enabled',
    'weather_season_mode',
    'weather_active_season_id',
    'weather_fallback_scope_type',
    'weather_fallback_scope_id'
);

DROP TABLE IF EXISTS `weather_overrides`;
DROP TABLE IF EXISTS `climate_zone_weather_weights`;
DROP TABLE IF EXISTS `climate_zone_season_profiles`;
DROP TABLE IF EXISTS `climate_assignments`;
DROP TABLE IF EXISTS `weather_types`;
DROP TABLE IF EXISTS `climate_zones`;
DROP TABLE IF EXISTS `seasons`;
DROP TABLE IF EXISTS `climate_areas`;
