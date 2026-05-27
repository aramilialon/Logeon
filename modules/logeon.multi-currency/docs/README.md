# Modulo Logeon Multi Currency

## Scopo

Modulo owner del dominio multi-valuta:

- CRUD admin valute aggiuntive;
- estensione runtime lista valute disponibili;
- estensione UI tramite slot twig;
- ownership schema `currencies`, `character_wallets`, `currency_logs`.

## Architettura target

- Core:
  - mantiene runtime economico minimo (single-currency fallback e servizi base).
- Modulo `logeon.multi-currency`:
  - possiede schema valuta/wallet/log;
  - espone endpoint admin multi-valuta;
  - estende runtime via hook (`currency.available_list`).

## Entry points

- `bootstrap.php`
  - registra view path modulo;
  - registra slot twig:
    - `twig.slot.character.profile.wallets`
    - `twig.slot.shop.price.extra`
  - registra hook runtime:
    - `currency.extra_wallets`
    - `currency.available_list`
  - registra endpoint map per frontend admin.
- `routes.php`
  - `POST /admin/multi-currencies/*`
  - alias compat `POST /admin/currencies/*`

## Ownership DB

- Migration install:
  - `migrations/001_install.sql`
- Migration uninstall:
  - `migrations/uninstall/001_uninstall.sql`

Tabelle owned dal modulo:

- `currencies`
- `character_wallets`
- `currency_logs`
