# Mini-guida XAMPP per Logeon in locale

## 1. Prerequisiti

- XAMPP installato in `C:\xampp`
- Progetto Logeon copiato in:

```txt
C:\xampp\htdocs\logeon
````

* Dipendenze installate:

```bash
composer install
```

* File configurati:

```txt
configs/config.php
configs/db.php
configs/app.php
```

Logeon prevede proprio questo setup minimo: copia progetto, `composer install`, configurazione dei file e inizializzazione DB tramite `/install` o import manuale. 

---

## 2. Configurare il VirtualHost Apache

Apri come amministratore:

```txt
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

Aggiungi:

```apache
<VirtualHost *:80>
    ServerName logeon.local
    DocumentRoot "C:/xampp/htdocs/logeon"

    <Directory "C:/xampp/htdocs/logeon">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/logeon.local-error.log"
    CustomLog "logs/logeon.local-access.log" common
</VirtualHost>
```

Assicurati che in:

```txt
C:\xampp\apache\conf\httpd.conf
```

sia attiva questa riga:

```apache
Include conf/extra/httpd-vhosts.conf
```

Se è commentata con `#`, rimuovi il `#`.

---

## 3. Configurare il file hosts di Windows

Apri come amministratore:

```txt
C:\Windows\System32\drivers\etc\hosts
```

Aggiungi in fondo:

```txt
127.0.0.1 logeon.local
```

Salva il file.

---

## 4. Configurare Logeon

In `configs/app.php` o nel file di configurazione equivalente, imposta la base URL locale:

```php
'baseurl' => 'http://logeon.local',
```

Verifica anche `configs/db.php`, ad esempio:

```php
'host' => '127.0.0.1',
'database' => 'logeon',
'username' => 'root',
'password' => '',
```

---

## 5. Avvio in locale

Dal pannello XAMPP avvia:

```txt
Apache
MySQL
```

Poi apri nel browser:

```txt
http://logeon.local
```

Per prima installazione:

```txt
http://logeon.local/install
```

Logeon supporta l’installazione guidata tramite `/install`, oppure l’import manuale dello schema `database/logeon_db_core.sql`. 

---

## 6. Verifica rapida

Da terminale:

```bash
C:\xampp\php\php.exe scripts/php/smoke-core-runtime.php
```

Questo comando è indicato nella documentazione come smoke test principale per verificare il runtime locale. 

---

## 7. Problemi comuni

### Il sito non si apre

Controlla:

* Apache avviato
* `logeon.local` presente nel file `hosts`
* VirtualHost scritto correttamente
* Apache riavviato dopo le modifiche

### Errore 500

Esegui:

```bash
C:\xampp\php\php.exe -l percorso/file.php
```

e fai hard refresh del browser. La guida troubleshooting consiglia anche `php -l`, `node --check` e lo smoke core runtime. 
