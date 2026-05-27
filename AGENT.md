# AGENT.md — Logeon

This file is the primary reference for AI agents working on this codebase.
Read it fully before making any change. Cross-reference the `docs/` directory for deep-dives.

---

## What is Logeon

Logeon is a PHP-based web platform for text role-playing games (GDR/LARP communities). It provides:
- A game area (`/game`) for players: character management, locations, chat, shops, quests, etc.
- An admin panel (`/admin`) for staff: users, content management, modules, settings.
- A module system for optional features that extend the platform without touching the core.

Runtime: XAMPP on Windows (PHP 8+, MySQL via mysqli, Apache).

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ (no framework — custom core) |
| DB adapter | MySQLi via `Core\Database\MysqliDbAdapter` |
| Templates | Twig (via `core/Template.php`) |
| Frontend | ESM JS (esbuild bundler), Bootstrap 5, Sass |
| Auth | Custom session guard (`core/SessionGuard.php`) |
| HTTP | Custom Router + RequestData + ResponseEmitter |

---

## Directory Structure

```
logeon/
├── app/
│   ├── controllers/        ← core controllers (standalone classes, no base class)
│   ├── Services/           ← business logic (PascalCase namespace App\Services)
│   ├── Models/             ← thin data models extending Core\Models
│   ├── Contracts/          ← domain interfaces (namespace App\Contracts)
│   ├── routes/
│   │   ├── api.php         ← all API routes (POST JSON endpoints)
│   │   └── game.php        ← HTML page routes for /game
│   └── views/
│       ├── admin/          ← admin panel templates
│       ├── app/            ← game area templates
│       ├── layouts/        ← shared layouts
│       └── sys/            ← system pages (install, error, etc.)
├── assets/
│   ├── js/
│   │   ├── app/
│   │   │   ├── core/       ← runtime bootstrap, registry, context
│   │   │   ├── features/   ← page feature controllers (game/* and admin/*)
│   │   │   ├── modules/    ← module factories (game/* and admin/*)
│   │   │   └── components/ ← shared UI components
│   │   ├── components/     ← standalone shared components
│   │   └── dist/           ← compiled bundles (do not edit directly)
│   ├── css/                ← compiled CSS (edit Sass source instead)
│   └── sass/               ← Sass source files
├── core/                   ← framework core (edit with extreme caution)
├── database/
│   ├── logeon_db_core.sql  ← canonical DB schema (single source of truth)
│   └── patches/            ← temporary SQL patches (integrated then deleted)
├── modules/                ← optional modules (Classe A and B)
├── configs/                ← config.php, db.php, app.php
├── scripts/
│   └── php/                ← smoke test scripts
└── uploads/                ← user-uploaded files
```

---

## Backend Patterns

### Controllers

Controllers are **standalone classes** — they do NOT extend any base class and do NOT extend `Model`.

```php
<?php
declare(strict_types=1);
namespace App\Controllers; // or no namespace for legacy controllers in app/controllers/

use Core\Http\AppError;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;
use Core\AuthGuard;
use Core\AppContext;

class MyController
{
    private ?MyService $service = null;

    private function service(): MyService
    {
        if (!$this->service) {
            $this->service = new MyService();
        }
        return $this->service;
    }

    public function myMethod(RequestData $request): array
    {
        AuthGuard::requireUser(); // or requireAdmin()
        $data = $request->input();
        $result = $this->service()->doSomething($data);
        return ResponseEmitter::json(['ok' => true, 'data' => $result]);
    }
}
```

Admin-only check: `AppContext::authContext()->isAdmin()` or `AuthGuard::requireAdmin()`.

### Services

Business logic lives in `app/Services/`. Services use the DB adapter directly via prepared statements.

```php
<?php
declare(strict_types=1);
namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class MyService
{
    private DbAdapterInterface $db;

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
    }

    public function getOne(int $id): array
    {
        $row = $this->db->fetchOnePrepared('SELECT * FROM my_table WHERE id = ?', [$id]);
        if (is_object($row)) return (array) $row;
        return is_array($row) ? $row : [];
    }

    public function getList(array $params = []): array
    {
        $rows = $this->db->fetchAllPrepared('SELECT * FROM my_table WHERE is_active = 1', []);
        return $rows ?: [];
    }
}
```

**Critical DB rules:**
- Use `fetchOnePrepared()` for single rows — never `$rows[0]`.
- Use `fetchAllPrepared()` for lists — always fallback with `$rows ?: []`.
- Use `executePrepared()` for INSERT/UPDATE/DELETE.
- Use `$this->db->lastInsertId()` after INSERT.
- Never concatenate user input into SQL — always use `?` placeholders.
- Use `AppError::validation($message, [], 'error_code')` for domain errors.

### Routes

All JSON API endpoints go in `app/routes/api.php`. HTML page routes in `app/routes/game.php`.

```php
// app/routes/api.php
$router->apiPost('/my-resource/list', [MyController::class, 'list']);
$router->apiPost('/my-resource/create', [MyController::class, 'create']);

// Admin routes grouped under /admin prefix
$router->apiPost('/admin/my-resource/list', [MyController::class, 'adminList']);
```

### Admin Datagrid Response Format

Admin list endpoints **must** return exactly this shape:

```json
{
  "dataset": [...],
  "properties": {
    "query": "",
    "page": 1,
    "results_page": 25,
    "orderBy": "name|ASC",
    "tot": 100
  }
}
```

### Error Handling

Use `AppError` for all domain errors — never `die()`, `echo json_encode()`, or raw exceptions.

```php
throw AppError::validation('Message', [], 'error_code');
throw AppError::notFound('Resource not found', 'not_found');
throw AppError::forbidden('Access denied', 'forbidden');
```

Error codes must be stable `snake_case` strings. See `docs/contratti-api-backend.md` for all registered codes.

### Models

Models extend `Core\Models` and contain **only schema metadata** — no business logic.

```php
class MyModel extends \Core\Models
{
    protected $table = 'my_table';
    protected $primary_key = 'id';
    protected $fillable = ['name', 'description', 'is_active'];
}
```

Domain interfaces belong in `app/Contracts/` (namespace `App\Contracts`).

---

## Frontend Patterns

### ESM — Mandatory for New Files

All new JS files use ESM (`import`/`export`). Never use IIFE or `window.*` assignments for new code.

```js
// Good
export function myHelper() { ... }
export default function createMyModule() { ... }

// Bad — legacy only
window.MyHelper = function () { ... };
```

Bundles are built with esbuild: `npm run build:frontend:pilot`.

### Module Factory Pattern

Every admin or game module is a factory function returning `{ mount, unmount }`:

```js
// assets/js/app/modules/admin/MyModule.js
const globalWindow = (typeof window !== 'undefined') ? window : globalThis;

function createAdminMyModule() {
    return {
        mount: function () {
            if (typeof globalWindow.AdminMyFeature !== 'undefined'
                && typeof globalWindow.AdminMyFeature.init === 'function') {
                globalWindow.AdminMyFeature.init();
            }
        },
        unmount: function () {}
    };
}

globalWindow.AdminMyModuleFactory = createAdminMyModule;
export { createAdminMyModule as AdminMyModuleFactory };
export default createAdminMyModule;
```

### Admin Registry — Three Mandatory Registration Points

Every new admin page requires registration in **all three** locations:

1. `MODULE_FACTORY_MAP` in `assets/js/app/core/admin.registry.js` — factory resolution
2. `getPageModules()` in `assets/js/app/core/admin.registry.js` — authoritative page→module routing
3. `modules` map in `assets/js/app/core/admin.runtime.js` — secondary (overwritten by registry at boot)

Omitting point 2 means the module never mounts, even if points 1 and 3 are present.

```js
// admin.registry.js — point 1
const MODULE_FACTORY_MAP = {
    'admin.my-feature': () => import('../modules/admin/MyModule.js').then(m => m.default || m.AdminMyModuleFactory),
    // ...
};

// admin.registry.js — point 2
function getPageModules(pageKey) {
    const PAGE_MODULES = {
        'my-feature': ['admin.my-feature'],
        // ...
    };
    return PAGE_MODULES[pageKey] || [];
}
```

### No Inline JS or CSS in Twig

Zero `<script>` blocks with logic and zero `style=""` attributes in `.twig` files.
Use `data-*` attributes to pass data from Twig to JS. All interactivity lives in feature JS files.

### UI Labels

All visible UI labels, buttons, placeholders, and badges must be in **Italian**.
Internal identifiers (enum values, capability names, error codes) stay in English.

### Uploader Widget

Any field storing a file URL (icon, image, thumbnail) must use the `Uploader.js` component with tab link/upload — never a plain text input.

---

## Admin Panel Structure

### Navigation (sidebar)

The admin sidebar is in `app/views/admin/layouts/aside.twig`. Hardcoded sections:

- `Utenti e personaggi`
- `Richieste e segnalazioni`
- `Oggetti`
- `Parametri ed entità`
- `Commercio`
- `Mondo e navigazione`
- `Narrativa`
- `Economia`
- `Gruppi e fazioni`
- `Comunicazione`
- `Documentazione`
- `Logs`

### Page Routing

The admin main area renders via `app/views/admin/dashboard.twig`. It uses `{% if current_page == 'X' %}` branches for known core pages. Modules and new features render via `{% else %}` using slot hooks.

**Reserved page names** (do NOT use as `page` values for new features):
`dashboard`, `users`, `characters`, `blacklist`, `maps`, `currencies`, `shops`, `conflicts`,
`narrative-events`, `narrative-states`, `system-events`, `character-lifecycle`,
`character-requests`, `locations`, `inventory-shop`, `jobs`, `jobs-tasks`, `jobs-levels`,
`guilds`, `guild-alignments`, `guilds-reqs`, `guilds-locations`, `guild-locations`, `guilds-events`,
`forums`, `forums-types`, `storyboards`, `rules`, `how-to-play`, `items`, `items-categories`,
`items-rarities`, `equipment-slots`, `item-equipment-rules`, `settings`,
`narrative-tags`, `narrative-delegation`, `narrative-delegation-grants`, `narrative-npcs`,
`message-reports`, `logs-conflicts`, `logs-currency`, `logs-experience`, `logs-fame`,
`logs-guild`, `logs-job`, `logs-location-access`, `logs-sys`, `logs-narrative`,
`themes`, `modules`

---

## Module System

### Taxonomy

**Classe A — Bundled Standard**: distributed with Logeon, extracted from the original core. Declare `"class": "bundled"` in `module.json`. Support only `activate`/`deactivate`. No `uninstall`/`purge`.

Current Classe A modules: `logeon.archetypes`, `logeon.attributes`, `logeon.factions`, `logeon.multi-currency`, `logeon.novelty`, `logeon.quests`, `logeon.social-status`, `logeon.weather`.

**Classe B — Optional Third-party**: fully additive schema (own tables only). Support full lifecycle: `install` / `activate` / `deactivate` / `uninstall` / `purge`. New modules are always Classe B.

### Isolation Rule

**A module never touches `/app/` or `/core/`.** All PHP (controllers, services, models, providers), routes, assets, and views live exclusively inside `modules/<vendor.module>/`. Core↔module communication happens only via `Core\Hooks`.

### Module Structure

```
modules/<vendor.module>/
├── module.json         ← mandatory manifest
├── bootstrap.php       ← PSR-4 autoloader + hook registration
├── routes.php          ← module routes (loaded only when active)
├── src/
│   ├── Controllers/
│   ├── Services/
│   ├── Models/
│   └── Provider/
├── migrations/
│   ├── install.sql     ← idempotent (IF NOT EXISTS)
│   └── uninstall.sql   ← rollback (Classe B only)
├── assets/
├── views/
└── docs/README.md
```

### module.json

```json
{
  "id": "vendor.module-name",
  "name": "Module Name",
  "version": "1.0.0",
  "vendor": "vendor",
  "description": "Brief description.",
  "dependencies": [],
  "compat": { "min": "0.8.0", "max": "" },
  "menus": {
    "admin": {
      "aside": [{ "label": "Label", "page": "vendor-page", "section": "Documentazione" }]
    }
  }
}
```

### Hook Communication

```php
// bootstrap.php — module side
\Core\Hooks::addFilter('hook.name', function ($default) {
    return new \Modules\Vendor\ModuleName\Provider\MyProvider();
});
```

The core calls `\Core\Hooks::filter('hook.name', null)` and uses the result as opaque data.

### Anti-patterns to Avoid

- Creating files in `/app/` or `/core/` for a module.
- Adding interfaces to `app/Contracts/` for a module.
- Adding module routes to `app/routes/api.php`.
- Hardcoding a module ID anywhere in core.
- Using a `section` name that nearly matches a known section (case-sensitive matching).
- Using a reserved `page` value that conflicts with core pages.

---

## Roles and Permissions

Hierarchy (descending): `Superuser` → `Admin` → `Moderatore` → `Master` → `Utente`.

- `/admin` is restricted to Admin role.
- Permissions are always enforced server-side — UI gating is secondary.
- Use `AuthGuard::requireAdmin()` in controllers for admin endpoints.
- Declarative UI permissions use `data-requires-*` attributes (see `docs/guida-permessi-ui-attributi.md`).

---

## Database

### Schema Management

- `database/logeon_db_core.sql` is the single canonical schema bootstrap. The installer imports only this file.
- `database/patches/*.sql` are temporary incremental patches. Once integrated into `logeon_db_core.sql`, patch files are deleted.
- All SQL must use `utf8mb4` charset and `utf8mb4_unicode_ci` collation.
- Tables use `InnoDB` engine.

### SQL Style

```sql
CREATE TABLE IF NOT EXISTS `my_table` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)  NOT NULL,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Security Conventions

- Never echo raw user input in PHP output.
- Never concatenate user input into SQL — always prepared statements with `?`.
- Use `Core\HtmlSanitizer` for any HTML content stored by users.
- For file uploads: whitelist extensions, validate MIME via `finfo`, generate unique stored names, never allow path traversal (`..`, leading `.`).
- Use `realpath()` + `strpos()` to guard against directory traversal.
- Block PHP execution in upload directories with `.htaccess` (`php_flag engine off`).
- Admin endpoints must check `AppContext::authContext()->isAdmin()`.
- Never expose internal error details to the client — use stable error codes only.

---

## Workflow: Adding a New Core Feature

1. Define behavior and permissions.
2. Write SQL patch in `database/patches/patch_<feature>.sql`.
3. Apply the patch to the local DB.
4. Implement `app/Services/<Feature>Service.php`.
5. Implement `app/controllers/<Feature>.php` (standalone, no base class).
6. Add routes to `app/routes/api.php`.
7. If admin page: add `{% elseif current_page == '<page>' %}` in `dashboard.twig`.
8. Update `aside.twig` with sidebar link.
9. Create Twig templates: `app/views/admin/pages/<feature>.twig` and any modals.
10. Create `assets/js/app/features/admin/<FeatureName>.js`.
11. Create `assets/js/app/modules/admin/<FeatureName>Module.js`.
12. Register in all three points: `MODULE_FACTORY_MAP`, `getPageModules()`, `modules` map.
13. Rebuild bundles: `npm run build:frontend:pilot`.
14. Integrate SQL patch into `logeon_db_core.sql` and delete the patch file.
15. Update `docs/contratti-api-backend.md` if new API contracts.

---

## Smoke Tests and Verification

Run before every commit:

```powershell
# PHP syntax check on modified files
php -l app/controllers/MyController.php

# Core smoke tests
C:\xampp\php\php.exe scripts/php/smoke-core-runtime.php
C:\xampp\php\php.exe scripts/php/smoke-core-db-runtime.php
C:\xampp\php\php.exe scripts/php/smoke-core-auth-runtime.php

# Lint
composer run lint:php
composer run psr:check

# JS syntax check
node --check assets/js/app/features/admin/MyFeature.js

# Frontend guardrails
node scripts/smoke-runtime-guardrails.mjs
```

After DB changes:

```powershell
# Apply a patch
C:\xampp\mysql\bin\mysql.exe -u root appdb -e "source database/patches/patch_my_feature.sql"

# Regenerate canonical schema dump (after integrating patches)
C:\xampp\mysql\bin\mysqldump.exe -u root --default-character-set=utf8mb4 --single-transaction --routines --triggers appdb --result-file=database/logeon_db_core.sql
```

---

## High-Risk Files

Modify these only with a targeted, well-scoped task. Touching them incidentally is a bug risk:

- `core/Models.php`
- `core/Router.php`
- `core/SessionGuard.php`
- `core/Template.php`
- `core/Database/MysqliDbAdapter.php`
- `core/Database/DbAdapterFactory.php`
- `app/Services/AuthService.php`
- `autoload.php`
- `app/routes.php`
- `assets/js/app/core/admin.registry.js`
- `assets/js/app/core/admin.runtime.js`
- `app/views/admin/dashboard.twig`
- `app/views/admin/layouts/aside.twig`

---

## Git and PR Conventions

Branch naming: `<type>/<area>-<short-description>`
Examples: `fix/admin-settings-toast-save`, `feat/location-position-tags`

Commit format: `<type>(<scope>): <summary>`
Examples: `fix(chat): clamp message length server-side`, `feat(shop): add bulk delete endpoint`

PR process:
1. Open as Draft as soon as scope is clear.
2. Move to Ready for Review only after smoke tests pass.
3. Preferred merge strategy: Squash and merge.
4. Update `docs/changelog.md` with `Aggiunto / Modificato / Bugfix / Verifica tecnica` sections.

---

## Key Documentation Reference

| Document | Purpose |
|---|---|
| `docs/guida-contributori.md` | Contributor workflow, mandatory rules, frontend/backend standards |
| `docs/guida-architettura-frontend.md` | Runtime layers, bootstrap, registry pattern, ESM bundler, CustomEvent decoupling |
| `docs/contratti-api-backend.md` | Full API contract map, transport conventions, all error codes |
| `docs/guida-sistema-moduli.md` | Module lifecycle, taxonomy, hook system, reserved page names |
| `docs/guida-creazione-moduli.md` | Step-by-step guide for creating a Classe B module |
| `docs/matrice-ruoli-permessi.md` | Role hierarchy and permission rules |
| `docs/guida-runtime-db-schema.md` | DB adapter, schema policy, smoke commands |
| `docs/guida-temi-layout.md` | Theme and layout customization |
| `docs/guida-autenticazione-sessioni.md` | Auth flow, session management |
| `docs/riferimento-componenti-frontend.md` | Shared frontend components (Datagrid, Uploader, SelectionGroup, etc.) |
| `docs/riferimento-servizi-frontend.md` | Frontend service modules API reference |
| `CONTRIBUTING.md` | Git workflow, PR policy, review lifecycle, code style examples |
