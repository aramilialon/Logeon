# Guida Go-Live GDPR per Istanze Logeon

Ultimo aggiornamento: 2026-05-18

## Scopo

Questa guida è destinata ai gestori che scaricano Logeon e aprono un PbC.  
Il core fornisce una base tecnica GDPR-ready, ma la conformità finale dipende da come viene configurata e gestita la singola istanza.

## Punto chiave

Logeon non è il titolare del trattamento del tuo PbC: lo sei tu (o la tua associazione/team).  
Di conseguenza, la responsabilità legale finale su testi, processi e risposte agli utenti resta in capo al gestore dell'istanza.

## Cosa è già coperto dal core

1. Consenso legale in registrazione (privacy + termini).
2. Versionamento consensi legali con log.
3. Consenso newsletter con storico variazioni.
4. Richieste privacy utente (export/cancellazione account) con workflow admin.
5. Export dati utente in JSON.
6. Cookie consent con opzioni granulari e log server-side.
7. Retention GDPR tecnica (manuale/admin e CLI schedulabile).
8. Pagine pubbliche privacy/termini/cookie già servite dal core.

## Cosa deve fare il gestore del PbC prima del go-live

## 1) Nomine e ruoli interni

1. Definire il titolare del trattamento (persona fisica o giuridica).
2. Nominare un referente privacy operativo.
3. Valutare se serve un DPO (quando richiesto dal contesto normativo applicabile).
4. Definire chi può gestire richieste GDPR in admin.

## 2) Testi legali e base giuridica

1. Personalizzare Privacy Policy, Termini e Cookie Policy con i dati reali del tuo progetto.
2. Inserire contatti privacy reali (email e referente).
3. Validare i testi con una figura legale competente.
4. Versionare ogni revisione documentale (es. `1.0.0`, `1.1.0`).

## 3) Configurazione applicativa obbligatoria

In `configs/app.php`, sezione `APP['legal']`, verificare:

1. `privacy_policy_url`
2. `terms_of_service_url`
3. `cookie_policy_url`
4. `privacy_policy_version`
5. `terms_of_service_version`
6. `cookie_policy_version`
7. `privacy_contact_name`
8. `privacy_contact_email`
9. parametri `retention` coerenti con la tua policy interna

## 4) Procedure organizzative minime

1. Procedura per richieste diritti interessato (accesso, rettifica, cancellazione, opposizione).
2. Procedura per gestione data breach (chi fa cosa, in quali tempi, con quali comunicazioni).
3. Procedura per gestione minori (se il tuo progetto li ammette).
4. Procedura per moderazione contenuti e conservazione log.

## 5) Operatività periodica

1. Eseguire retention GDPR con frequenza pianificata.
2. Verificare periodicamente i ruoli staff con accesso a dati personali.
3. Testare periodicamente export dati e workflow richieste GDPR.
4. Aggiornare testi legali quando cambiano finalità, strumenti o fornitori.

## Checklist rapida pre-pubblicazione

Segna ogni punto come completato:

1. Titolare e referente privacy definiti.
2. Privacy/Termini/Cookie policy personalizzate e validate.
3. Versioni policy impostate in configurazione.
4. Contatto privacy reale e funzionante.
5. Patch DB GDPR applicate.
6. Endpoint e UI richieste GDPR testati.
7. Export JSON verificato con utente test.
8. Retention schedulata (admin o CLI).
9. Procedura data breach documentata.
10. Ruoli staff verificati sui dati sensibili.

## Serve una figura legale?

Nella pratica: sì, è fortemente consigliata.  
Il core può coprire la parte tecnica, ma non può sostituire:

1. validazione dei testi legali;
2. corretta base giuridica dei trattamenti;
3. valutazione obblighi specifici del tuo paese/contesto;
4. adeguatezza delle procedure organizzative.

## Servono altri strumenti nell'app?

Per la baseline, no: il core copre già i blocchi principali.  
Sono opzionali solo strumenti di governance (reportistica, reminder audit, dashboard compliance), utili ma non obbligatori per partire.

## Riferimenti

1. `docs/guida-privacy-gdpr.md`
2. `docs/guida-installazione-produzione.md`
3. `docs/matrice-ruoli-permessi.md`
