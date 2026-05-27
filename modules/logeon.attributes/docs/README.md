# Logeon Attributes Module

## Scopo
`logeon.attributes` incapsula il dominio Attributi personaggio:
- provider runtime via hook `attribute.provider`;
- endpoint API admin/profilo `/admin/character-attributes/*` e `/profile/attributes/*`;
- viste admin e modale profilo Attributi.

Con modulo attivo il comportamento resta equivalente alla versione core precedente.

## Integrazione core
- Hook provider: `attribute.provider`
- Slot Twig admin: `twig.slot.admin.dashboard.character-attributes`
- Slot Twig game: `twig.slot.game.profile.modals`
- Slot Twig game (card profilo): `twig.slot.game.profile.metrics.cards`

## Entrypoints
- `bootstrap.php`
- `routes.php`
- `migrations/001_install.sql`
- `migrations/uninstall/001_uninstall.sql`

## Note operative
- Il provider modulo delega a `CharacterAttributesFacadeService`.
- Con modulo disattivo il core usa `CoreAttributeProvider` come fallback no-op.
