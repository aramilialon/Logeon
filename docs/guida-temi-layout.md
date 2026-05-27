# Guida Temi e Layout

Ultimo aggiornamento: 2026-04-09

## Scopo
Personalizzare aspetto e shell di `public` e `game` senza modificare il core.

## Limite importante
L'area `admin` non supporta override strutturale via tema.

## Struttura base

```text
custom/themes/<theme-id>/
  theme.json
  views/
    layouts/
    app/layouts/
  assets/
    css/
    js/
    images/
    fonts/
```

## Manifest minimo (`theme.json`)

```json
{
  "id": "my-theme",
  "name": "My Theme",
  "version": "1.0.0",
  "compat": { "core": ">=0.7.0 <1.0.0" },
  "shell": {
    "public_layout": "layouts/theme-public.twig",
    "game_layout": "app/layouts/theme-game.twig"
  },
  "body_class": {
    "shared": ["theme-my-theme", "theme-shell"],
    "public": ["theme-public-surface"],
    "game": ["theme-game-surface"]
  },
  "assets": {
    "shared_css": ["css/shared.css"],
    "shared_js": ["js/shared.js"],
    "public_css": ["css/public.css"],
    "public_js": ["js/public.js"],
    "game_css": ["css/game.css"],
    "game_js": ["js/game.js"]
  }
}
```

## Configurazione tema attivo
1. `sys_configs.active_theme` (precedenza più alta)
2. `APP.theme.active_theme` in `configs/app.php`
3. fallback core (nessun tema attivo)

## Funzioni Twig disponibili
1. `theme_active()`
2. `theme_id()`
3. `theme_meta([key])`
4. `theme_asset(path)`
5. `theme_assets(channel)` con channel: `shared_css`, `shared_js`, `public_css`, `public_js`, `game_css`, `game_js`
6. `theme_body_classes()`
7. `theme_shell(area, fallback)`

## Comportamento asset condivisi
1. `theme_assets('public_css')` carica `shared_css` e poi `public_css`
2. `theme_assets('game_css')` carica `shared_css` e poi `game_css`
3. `theme_assets('public_js')` carica `shared_js` e poi `public_js`
4. `theme_assets('game_js')` carica `shared_js` e poi `game_js`

## Body classes automatiche
Il runtime aggiunge sempre al tag `<body>`:
1. `theme-context-public` oppure `theme-context-game`
2. `theme-active` + `theme-<theme-id>` quando un tema e attivo
3. classi opzionali da `body_class`

`body_class` supporta:
1. stringa singola: `"body_class": "theme-foo hero-shell"`
2. array semplice: `"body_class": ["theme-foo", "hero-shell"]`
3. oggetto per contesto: chiavi `shared`, `all`, `default`, `public`, `game`

## Override e fallback template
Ordine di lookup:
1. `custom/themes/<active_theme>/views`
2. `custom/views` (fallback legacy)
3. `app/views` (core)

## Requisiti shell
I template shell dichiarati in `theme.json` devono:
1. esistere in `views/`
2. terminare con `.twig`
3. esporre almeno un blocco shell/content (`content`, `public_page_content`, `game_page_content`, `public_shell`, `game_shell`)
4. (contract v1) estendere il layout core corrispondente:
   - `public_layout` -> `{% extends 'layouts/layout.twig' %}`
   - `game_layout` -> `{% extends 'app/layouts/layout.twig' %}`

## Validazione tema
Script CLI:

```bash
C:\xampp\php\php.exe scripts/php/themes-validate.php
```

## Guardrail
1. niente business logic nel tema;
2. niente path traversal (`..`) nei path tema;
3. non alterare i mount runtime di modali/offcanvas/toast;
4. mantenere compatibilità con `module_assets` e `module_slot`.
