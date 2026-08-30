# Guida installazione da sorgente (dal clone al go-live)

Ultimo aggiornamento: 2026-08-30

## Scopo
Fornire una procedura unica, passo passo, per partire dal codice sorgente di Logeon e arrivare a un sito online funzionante.

## 1) Prerequisiti
### Ambiente locale (sviluppo e preparazione)
1. Git.
2. PHP 8.2.x.
3. Composer 2.x.
4. MySQL o MariaDB.
5. Node.js 20+ e npm (solo se devi rigenerare i bundle frontend).
6. Un web server locale (Apache/Nginx oppure XAMPP).

### Ambiente server (produzione)
1. Hosting/VPS con PHP 8.2.x e supporto a rewrite (`.htaccess`) oppure regole equivalenti su Nginx.
2. Database MySQL/MariaDB dedicato.
3. HTTPS attivo sul dominio pubblico.
4. Accesso SSH/FTP/SFTP per caricare i file.

## 2) Clonare il repository
1. Apri una shell nella cartella di lavoro.
2. Clona il repository:
   ```bash
   git clone https://github.com/aramilialon/Logeon.git
   ```
3. Entra nella root progetto:
   ```bash
   cd Logeon
   ```

## 3) Installare le dipendenze
1. Installa dipendenze PHP:
   ```bash
   composer install
   ```
2. Se devi aggiornare i bundle frontend, installa anche dipendenze Node:
   ```bash
   npm install
   ```

## 4) Configurare il progetto
1. Verifica `configs/config.php`:
   - in locale: `CONFIG['debug'] = true`;
   - in produzione: `CONFIG['debug'] = false`.
2. Aggiorna `configs/app.php` con:
   - `APP['baseurl']` uguale al dominio finale;
   - contatti e metadati del tuo progetto;
   - (opzionale) configurazione PWA e policy legali.
3. Prepara la configurazione DB:
   - crea `configs/db.php` partendo da `configs/db.example.php`;
   - imposta host, nome database, utente, password e `crypt_key`.

## 5) Preparare il database
1. Crea un database vuoto (es. `logeon`).
2. Avvia l'installer web:
   - `https://tuo-dominio/install` (oppure dominio locale in sviluppo).
3. Completa il wizard fino alla finalizzazione.
4. In alternativa, importa manualmente lo schema:
   - `database/logeon_db_core.sql`.

## 6) Verifiche locali prima della pubblicazione
1. Lint PHP:
   ```bash
   composer lint:php
   ```
2. Smoke test core:
   ```bash
   php scripts/php/smoke-core-db-runtime.php
   php scripts/php/smoke-core-auth-runtime.php
   php scripts/php/smoke-core-runtime.php
   ```
3. Se hai modificato il frontend, rigenera i bundle:
   ```bash
   npm run build:frontend:release
   ```
4. Controlla che le pagine principali rispondano:
   - `/`
   - `/game`
   - `/admin`

## 7) Preparare il pacchetto per la messa online
Hai due opzioni.

### Opzione A - Deploy diretto del sorgente
1. Carica il progetto sul server.
2. Sul server esegui:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Verifica che `configs/config.php`, `configs/app.php`, `configs/db.php` siano corretti per l'ambiente produzione.

### Opzione B - Pacchetto release pronto
1. Dalla root progetto, genera lo zip `ready`:
   ```powershell
   powershell -ExecutionPolicy Bypass -File scripts/release/build-core-zip.ps1 -Variant ready
   ```
2. Usa `dist/release/logeon-core-ready.zip` per il deploy.

## 8) Pubblicare online
1. Carica i file nella document root del dominio.
2. Associa il database di produzione.
3. Imposta i permessi di scrittura per le cartelle runtime (`tmp/`, log, upload) secondo il tuo hosting.
4. Verifica rewrite URL e routing applicativo.
5. Abilita HTTPS e forza redirect HTTP->HTTPS.

## 9) Check finale post go-live
1. Login utente funzionante.
2. Accesso area gioco (`/game`) funzionante.
3. Accesso area admin (`/admin`) funzionante.
4. Smoke test core senza errori.
5. Backup DB pianificato.
6. `CONFIG['debug'] = false` in produzione.

## Riferimenti correlati
1. `docs/guida-installazione-produzione.md`
2. `docs/guida-build-release.md`
3. `docs/guida-backup-ripristino.md`
4. `docs/guida-go-live-gdpr-per-istanze.md`
