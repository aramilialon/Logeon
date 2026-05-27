# Guida Privacy e GDPR

Ultimo aggiornamento: 2026-05-17

## Scopo

Questa guida riassume il perimetro privacy/GDPR disponibile nel core Logeon e i punti da configurare prima della messa in produzione.

## Cosa include il core

1. Consenso legale obbligatorio in registrazione (privacy policy + termini del servizio).
2. Versionamento del consenso legale con storico eventi.
3. Preferenze newsletter con consenso opzionale e storico variazioni.
4. Revoca sessioni attive da area impostazioni utente.
5. Cancellazione personaggio con finestra reversibile.
6. Richieste privacy utente (esportazione dati / cancellazione account) con tracking stato.
7. Export dati utente in JSON da area impostazioni.
8. Pagine legali pubbliche:
   - `/privacy-policy`
   - `/terms-of-service`
   - `/cookie-policy`
9. Banner cookie pubblico con scelte granulari.

## Configurazione richiesta

Configura `APP['legal']` in `configs/app.php`:

1. `privacy_policy_url`
2. `terms_of_service_url`
3. `cookie_policy_url`
4. `privacy_policy_version`
5. `terms_of_service_version`
6. `cookie_policy_version`
7. `privacy_contact_name`
8. `privacy_contact_email`

Aggiorna sempre le versioni quando modifichi i testi legali.

## Tabelle DB coinvolte

1. `user_legal_consents`
2. `legal_consent_logs`
3. `gdpr_requests`
4. `cookie_consent_logs`
5. `user_mail_preferences`
6. `mail_consent_logs`

Le prime quattro vengono create dalla patch:

`database/patches/patch_gdpr_foundation.sql`

## Endpoint core privacy

Area utente (`/api/settings/*`):

1. `POST /privacy/context`
2. `POST /privacy/requests/list`
3. `POST /privacy/requests/create`
4. `POST /privacy/export`

Area pubblica:

1. `POST /api/privacy/cookie/consent`

Area admin (`/api/admin/*`):

1. `POST /gdpr/requests/list`
2. `POST /gdpr/requests/update-status`
3. `POST /gdpr/requests/execute`
4. `POST /gdpr/requests/export-payload`
5. `POST /gdpr/retention/run`

## Gestione operativa richieste

Da `/admin/gdpr-requests` lo staff puo:

1. Filtrare richieste per stato e tipo.
2. Cambiare stato manualmente (workflow interno).
3. Eseguire operazioni reali (solo superuser):
   - `export_data`: genera e salva snapshot JSON nel campo `gdpr_requests.payload_json`.
   - `delete_account`: anonimizza account utente, revoca sessioni e pianifica la cancellazione dei personaggi.
4. Scaricare il payload export generato (solo superuser).
5. La colonna email nella lista richieste admin viene mostrata solo ai superuser.

Protezione importante:

1. La procedura di cancellazione GDPR via admin non permette l'anonimizzazione del superuser creatore.

## Retention automatica

Il core include una manutenzione retention GDPR con regole conservative:

1. pulizia `cookie_consent_logs`
2. pulizia `mail_consent_logs`
3. pulizia `legal_consent_logs`
4. scrub del payload JSON export nelle richieste GDPR storiche
5. eliminazione richieste GDPR chiuse oltre soglia retention

Configurazione default in `configs/app.php`:

1. `APP['legal']['retention']['interval_minutes']`
2. `APP['legal']['retention']['cookie_consent_log_days']`
3. `APP['legal']['retention']['mail_consent_log_days']`
4. `APP['legal']['retention']['legal_consent_log_days']`
5. `APP['legal']['retention']['gdpr_request_payload_days']`
6. `APP['legal']['retention']['gdpr_request_closed_days']`

Esecuzione:

1. Da admin, sezione richieste GDPR, pulsante `Retention GDPR`.
2. Da CLI schedulata:
   `C:\xampp\php\php.exe scripts/php/gdpr-retention-maintenance.php`

## Note operative

1. La parte testuale legale va sempre validata dal responsabile legale del progetto.
2. L'export JSON core e un baseline operativo: puo essere esteso con dati di moduli opzionali.
3. I moduli non bundled dovrebbero dichiarare il proprio impatto privacy in `module.json` con un blocco `privacy`.
4. L'audit moduli (`/admin/modules` -> `Audit`) segnala `privacy_missing_declaration` e `privacy_incomplete_declaration`.
5. Il banner cookie pubblico supporta tre scelte (accetta tutto, rifiuta opzionali, personalizza) e registra ogni scelta in `cookie_consent_logs`.
6. In area admin, i dati email sensibili legati a richieste GDPR e blacklist sono visibili solo ai superuser.

## Guida per i gestori di istanza

Per il passaggio finale di conformita lato singolo PbC (ruoli, testi legali, processi organizzativi e checklist go-live) vedi:

`docs/guida-go-live-gdpr-per-istanze.md`
