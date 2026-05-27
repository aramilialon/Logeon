# Modulo Logeon Weather

## Scopo

Modulo meteo avanzato: estende il meteo core con clima, stagioni, profili, assegnazioni e override avanzati.

## Architettura target

- Core:
  - mantiene meteo essenziale (resolver base + endpoint base + widget base).
- Modulo `logeon.weather`:
  - possiede schema e config avanzate;
  - registra provider avanzato tramite hook `weather.provider`;
  - espone endpoint admin avanzati via `routes.php`.

## Entry points

- `bootstrap.php`
  - registra view path twig modulo;
  - registra slot dashboard/admin weather;
  - registra provider meteo modulo via hook.
- `routes.php`
  - registra endpoint avanzati sotto `/weather/*`:
    - climate areas
    - weather types
    - seasons
    - climate zones
    - profiles/weights
    - assignments
    - advanced overrides

## Ownership DB

- Migration install:
  - `migrations/001_install.sql`
- Migration uninstall:
  - `migrations/uninstall/001_uninstall.sql`

Tabelle owned dal modulo:

- `climate_areas`
- `climate_assignments`
- `climate_zone_season_profiles`
- `climate_zone_weather_weights`
- `climate_zones`
- `seasons`
- `weather_overrides`
- `weather_types`

Config advanced owned dal modulo:

- `weather_climate_enabled`
- `weather_season_mode`
- `weather_active_season_id`
- `weather_fallback_scope_type`
- `weather_fallback_scope_id`
