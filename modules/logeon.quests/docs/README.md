# Modulo Logeon Quests

## Scopo
Questo modulo espone il dominio Quest come feature opzionale:
1. trigger runtime quest via `QuestTriggerService::bootstrap()` (listener su hook core);
2. endpoint API quest (`/quests/*`) e admin (`/admin/quests/*`) caricati da `routes.php`;
3. viste Quest game/admin caricate da `views/` tramite slot Twig;
4. ownership schema/config quest via migration modulo.

## Integrazione runtime
1. `module.json` dichiara `entrypoints.bootstrap` e `entrypoints.routes`.
2. `bootstrap.php` registra:
   - autoloader PSR-4 del modulo;
   - hook Twig tramite `QuestModuleBootstrap::registerHooks()`:
     - `twig.view_paths`
     - `twig.slot.game.modals`
     - `twig.slot.game.navbar.organizations.after_bank`
     - `twig.slot.game.offcanvas.mobile.quests`
     - `twig.slot.admin.narrative_events.type_filter_options`
     - `twig.slot.admin.narrative_tags.entity_type_options`
     - `twig.slot.admin.dashboard.quests`
   - hook runtime:
     - `landing.metrics` (metrica modulo homepage)
     - `system_event.delete.related` (cleanup link quest<->eventi sistema)
     - `narrative_tags.entity_type_aliases`
     - `narrative_tags.entity_exists`
     - `narrative_tags.search_entities`
   - bootstrap trigger quest tramite `QuestModuleBootstrap::bootstrapTriggers()`.
3. `routes.php` registra le route quest solo quando il modulo e attivo:
   - `/quests/*`
   - `/admin/quests/*`
   - `/game/quests/history`
4. `views/` contiene i template quest game/admin estratti dal core.
5. `migrations/` contiene:
   - schema `quest_*` e `system_event_quest_links` (`001_install.sql`);
   - config chiavi `quests_*` (`001_install.sql`);
   - purge schema + config (`uninstall/001_uninstall.sql`).

## Note operative
- Con modulo OFF:
  - route quest assenti;
  - nessun listener trigger quest attivo;
  - nessun rendering UI quest in navbar/offcanvas/dashboard admin.
- Con modulo ON:
  - API e pagine quest operative con comportamento equivalente al legacy.
- Nessuna dipendenza hardcoded del core verso ID modulo o classi modulo.
