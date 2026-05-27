# Changelog

Questo changelog riassume le modifiche introdotte tra la release pubblica precedente `v0.9.3` e la release `v1.0.0`.

## v1.0.0 - 2026-05-27

### Nuovo: sistema aggiornamenti core (Updater)

1. Introdotto il flusso completo di aggiornamento applicativo lato admin con controlli, download, preflight, applicazione e rollback.
2. Aggiunta la pagina admin dedicata: `Admin > Sistema > Aggiornamenti`.
3. Introdotti i servizi core di update:
   - `UpdateCheckService`
   - `UpdateDownloadService`
   - `UpdatePreflightService`
   - `UpdateApplyService`
   - `UpdateRollbackService`
   - `UpdateManifestService`
   - `UpdateBackupService`
   - `UpdateLogService`
4. Aggiunti manifest di distribuzione e metadati release:
   - `logeon.manifest.json`
   - `update-manifest.json`
   - `configs/distribution.php`
   - `core/ReleaseInfo.php`
5. Introdotte patch/migrazioni DB per tracking aggiornamenti.

### Nuovo: baseline GDPR e privacy operativa

1. Introdotti servizi dedicati GDPR e retention:
   - `GdprService`
   - `GdprRetentionService`
   - `AdminComplianceNoticeService`
2. Aggiunti endpoint e UI per richieste privacy utente (export/cancellazione) e gestione admin.
3. Aggiunte pagine pubbliche legali e banner cookie lato frontend.
4. Inserita documentazione operativa per go-live GDPR e responsabilità istanza.

### Nuovo: sistema mail nativo (senza dipendenza PHPMailer)

1. Introdotta architettura mail nativa con trasporti:
   - `MailTransportInterface`
   - `NativeMailTransport`
   - `SmtpMailTransport`
2. Aggiunti servizi applicativi:
   - template mail
   - liste distribuzione
   - campagne
   - coda invio
   - log invii
   - preferenze utente
3. Aggiunti worker/script dedicati alla coda mail.
4. Rimossa la libreria `core/plugins/PHPMailer/*` dal core.

### Nuovo: media manager e contenuti

1. Aggiunti modello, servizi, controller e UI per gestione media in area admin.
2. Inserite patch DB per storage metadati media.

### Migliorato: gameplay, narrativa e strumenti staff

1. Rafforzata la gestione conflitti e strumenti di supporto location.
2. Estensione della gestione stati narrativi e integrazione nei flussi di gioco/staff.
3. Miglioramenti su inventario/equipaggiamento e regole slot.
4. Miglioramenti a dashboard gioco/admin e a diverse modali operative.

### Migliorato: moduli bundled (cutover e coerenza core)

1. Allineati i moduli bundled:
   - `logeon.archetypes`
   - `logeon.attributes`
   - `logeon.factions`
   - `logeon.multi-currency`
   - `logeon.novelty`
   - `logeon.quests`
   - `logeon.social-status`
   - `logeon.weather`
2. Aggiunte migration `install/uninstall` per i moduli bundled.
3. Riorganizzazione views/percorsi module-first per maggiore isolamento dal core.

### Migliorato: frontend, UX e performance

1. Aggiornate pipeline e bundle JS/CSS (admin/game/public/runtime).
2. Migliorata gestione bootstrap runtime, loader feature e registri moduli frontend.
3. Aggiunti componenti per overlay avvio, registrazione PWA e gestione asset versioning.
4. Rifiniture stile e coerenza UI su pagine admin/game.

### Build, release e qualità

1. Aggiornati script release per pacchetti più puliti e coerenti con i canali.
2. Aggiunto preflight release ufficiale.
3. Aggiunto sync manifest con checksum/size per pacchetti.
4. Aggiornati script di smoke test e tooling QA.

### Database: patch principali introdotte in v1.0.0

1. `patch_core_update_tables.sql`
2. `patch_gdpr_foundation.sql`
3. `patch_user_mail_preferences.sql`
4. `patch_mail_templates.sql`
5. `patch_mail_distribution_lists.sql`
6. `patch_mail_messages.sql`
7. `patch_mail_recipients.sql`
8. `patch_mail_queue.sql`
9. `patch_mail_queue_phase4.sql`
10. `patch_mail_logs.sql`
11. `patch_media_manager.sql`
12. `patch_staff_location_tools.sql`
13. `patch_users_superuser_roles.sql`
14. `patch_location_ambient_music.sql`
15. `patch_location_music_state.sql`
16. `patch_core_currencies_recovery.sql`
17. `patch_admin_performance_indexes.sql`

### Note di compatibilità

1. Release target: `1.0.0`.
2. Manifest canale stable aggiornato per pacchetti `ready` e `source-dev`.
3. In produzione, usare solo patch e configurazioni previste per il canale distribuito.
