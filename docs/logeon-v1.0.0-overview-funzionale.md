# Logeon v1.0.0 - Overview funzionale della release
  
Versione: `1.0.0`  
Stato: release finale della fase evolutiva iniziale

## Introduzione

Questa guida è pensata per chi non conosce ancora Logeon e vuole capire, in modo concreto, cosa può fare con la piattaforma.

Non è un documento tecnico e non è un changelog puntuale di commit.  
È una panoramica funzionale, narrativa e pratica della release `v1.0.0`: mostra come Logeon accoglie nuovi utenti, sostiene il gioco quotidiano, aiuta lo staff a governare una community e offre una base solida per evoluzioni future.

L’obiettivo è semplice: dare una visione chiara del valore della release, non solo un elenco di funzioni.

## In sintesi

Logeon v1.0.0 è una base completa per creare, avviare e governare una community play-by-chat.

La release copre le aree fondamentali di una land moderna:

1. area pubblica e contenuti informativi;
2. registrazione, login, verifica email e recupero password;
3. accesso Google e gestione multi-personaggio;
4. area gioco con chat, location, profili, inventario, forum e notifiche;
5. economia, shop, banca, lavori, gilde e fazioni;
6. strumenti narrativi per quest, eventi, stati, conflitti e archivi;
7. pannello staff/admin per gestione utenti, contenuti, mondo, economia e log;
8. suite email per comunicazione continuativa con la community;
9. installazione guidata e personalizzazione della land;
10. sistema modulare con estensioni opzionali installabili separatamente.

Il valore principale della v1.0.0 non è avere “tanti pannelli”, ma avere un ecosistema coerente: ogni area contribuisce a rendere la community più leggibile, stabile e sostenibile.

## A chi è rivolto questo documento

Puoi leggerlo dall’inizio alla fine oppure usarlo come mappa rapida.

| Profilo | Cosa conviene leggere prima |
|---|---|
| Founder o admin | Visione release, Area Pubblica, Area Staff/Admin, Comunicazione, Installazione |
| Staff narrativo | Area Gioco, Narrativa, Quest, Eventi, Conflitti |
| Moderatore | Governance, Log, Gestione utenti/personaggi, Segnalazioni |
| Giocatore curioso | Area Pubblica, Accesso, Area Gioco |
| Sviluppatore o contributor | Sistema modulare, perimetro release base, moduli opzionali |

## Cosa è incluso nella release base

Il corpo principale di questo documento descrive la release base di Logeon v1.0.0.

Sono inclusi:

1. il core applicativo;
2. le funzionalità ufficiali già presenti nella distribuzione base;
3. gli strumenti principali per area pubblica, gioco, staff, comunicazione, economia, narrativa e installazione.

La distinzione tra core e funzionalità bundled è quasi invisibile per l’utente finale: ciò che conta è che la release base fornisce già un ecosistema operativo completo.

## Cosa non è incluso nella release base

I moduli opzionali non bundled non fanno parte del pacchetto standard v1.0.0.

Sono estensioni scaricabili e installabili separatamente, pensate per ampliare domini specifici: combattimento avanzato, crafting, economia estesa, sondaggi, classificazioni narrative più complesse o logiche specialistiche.

Questa separazione è importante perché evita ambiguità: la release base resta completa e utilizzabile, mentre i moduli opzionali servono ad aumentare profondità e specializzazione dove necessario.

---

## 1) Visione della release v1.0.0

Logeon v1.0.0 non è una demo e non è un prototipo.

È una piattaforma completa che copre il ciclo di vita essenziale di una community play-by-chat:

1. arrivo di nuovi utenti;
2. ingresso nel gioco;
3. attività quotidiana dei giocatori;
4. lavoro operativo dello staff;
5. crescita organizzativa della community;
6. comunicazione continuativa;
7. governance e tracciabilità.

In pratica, con questa versione puoi passare da “idea di land” a “ecosistema funzionante” senza dover inseguire integrazioni esterne per ogni esigenza fondamentale.

La release mette insieme tre livelli:

1. **esperienza pubblica**, per presentare la land e ridurre la frizione di ingresso;
2. **esperienza di gioco**, per sostenere scene, relazioni, progressione e narrativa;
3. **direzione operativa**, per permettere allo staff di amministrare, moderare e far crescere il progetto.

---

## 2) Area Pubblica: il primo incontro con la community

L’Area Pubblica è il volto del progetto.

Qui si decide se un visitatore resta, si registra e inizia a giocare.

### Homepage

La homepage è pensata per orientare subito:

1. comunica che il progetto è vivo;
2. mostra contenuti e aggiornamenti;
3. dà un senso di identità;
4. invita all’azione senza confondere.

**Impatto pratico**  
La home non resta una pagina statica: diventa una porta d’ingresso narrativa, dove chi arriva percepisce subito direzione, cura e attività reale.

**Esempio**  
Un utente arriva per la prima volta, vede ambientazione attiva, contenuti aggiornati e movimento della community. In pochi minuti capisce che non sta entrando in un progetto abbandonato, ma in una land governata.

### Regolamento, Storyboard e Come giocare

Queste tre aree rispondono a tre bisogni diversi:

1. **Regolamento**: crea chiarezza e sicurezza.
2. **Storyboard/Ambientazione**: genera coinvolgimento emotivo.
3. **Come giocare**: riduce il timore di non sapere da dove iniziare.

**Perché è utile**  
Un giocatore nuovo può orientarsi in autonomia. Lo staff, di conseguenza, spende meno tempo in spiegazioni ripetitive e più tempo in accoglienza, gioco e qualità narrativa.

### Accessibilità cross-device e PWA

Logeon può essere installato come app web progressiva, rendendo l’accesso più immediato da desktop e mobile.

**Risultato**  
L’utente può aprire il gioco con la stessa naturalezza con cui aprirebbe un’app abituale, aumentando continuità e frequenza d’uso.

---

## 3) Accesso, account e onboarding

L’onboarding determina quante persone arrivano davvero al gioco.

Una land può avere ambientazione forte e strumenti ricchi, ma perdere utenti se l’ingresso è lento, fragile o poco chiaro. Logeon v1.0.0 affronta questo punto con un flusso account completo.

### Registrazione, login e recupero password

Il flusso account include:

1. registrazione standard;
2. verifica email;
3. login;
4. recupero password.

**Impatto pratico**  
La frizione iniziale si abbassa e diminuisce il rischio di abbandono nei primi minuti.

### Accesso Google

Il login Google è utile soprattutto per utenti meno tecnici o per accessi da mobile.

**Esempio**  
Una persona scopre la land da smartphone, usa Google per accedere e completa l’onboarding senza blocchi tecnici.

### Account con più personaggi

Un account può gestire più personaggi, in base alle impostazioni della land.

**Cosa abilita**  
La piattaforma può sostenere personaggi principali, secondari o ruoli di supporto senza obbligare l’utente a creare account separati.

**Esempio**  
Un utente interpreta un personaggio civile nella vita quotidiana e un personaggio militare nelle trame di conflitto. Può cambiare personaggio in ingresso, mantenendo un’unica identità account.

### Sicurezza e sessioni

Sono presenti strumenti di protezione sugli accessi e sulle sessioni.

**Risultato**  
Aumentano affidabilità percepita, controllo operativo e serenità per utenti e staff.

---

## 4) Area Gioco: esperienza quotidiana completa

L’Area Gioco è il cuore della piattaforma.

Qui Logeon sostiene le attività ricorrenti della community: entrare, orientarsi, giocare, comunicare, consultare informazioni, gestire il personaggio e seguire gli sviluppi narrativi.

### Home di gioco e orientamento rapido

La home interna aiuta il giocatore a riprendere subito il filo:

1. dove si trovava;
2. cosa è successo;
3. cosa richiede attenzione;
4. dove conviene andare.

**Impatto pratico**  
Riduce l’effetto “mi sono perso”, soprattutto per chi entra a sessioni brevi o dopo qualche giorno di assenza.

### Profilo personaggio e gestione personale

Il profilo permette di gestire identità, preferenze e informazioni del personaggio.

**Perché conta**  
La scheda personaggio non è solo un foglio statico: diventa uno spazio vivo in cui identità, disponibilità, relazioni e stato del personaggio aiutano il gioco quotidiano.

**Esempio**  
Un giocatore aggiorna disponibilità e visibilità. Gli altri utenti capiscono meglio quando e come interagire, con meno fraintendimenti.

### Mappe, location e presenza

Il mondo di gioco è organizzato in mappe e luoghi con presenza contestuale.

**Cosa abilita**  
La geografia diventa parte attiva della narrazione. Entrare in una location non significa solo cambiare pagina: significa dichiarare un contesto di scena.

**Risultato**  
Ordine narrativo, coerenza spaziale e immersione migliorano.

### Chat di location e sussurri

La chat di scena supporta:

1. interazione pubblica;
2. comunicazioni riservate;
3. letture e policy di accesso.

**Impatto pratico**  
Scene affollate o stratificate possono restare leggibili anche quando alcuni personaggi portano avanti accordi, segreti o sottotrame parallele.

### Messaggi privati

I messaggi privati completano il gioco fuori scena:

1. organizzazione;
2. relazioni;
3. coordinamento tra giocatori;
4. contatti tra personaggi.

**Risultato**  
La rete sociale della land continua anche quando non è in corso una scena pubblica.

### Archivi chat condivisibili

Le chat possono essere archiviate e condivise in modo ordinato.

**Perché è utile**  
La community costruisce una memoria narrativa consultabile, utile sia ai giocatori sia allo staff.

**Esempio**  
Dopo un evento importante, l’archivio diventa materiale di riferimento per sviluppi futuri.

### Forum

Il forum serve per contenuti che richiedono continuità:

1. comunicazioni ufficiali;
2. discussioni di community;
3. raccolte narrative;
4. organizzazione interna.

**Impatto pratico**  
Il tempo veloce della chat e il tempo lungo della community restano separati, ma collegati.

### Inventario, equipaggiamento e uso oggetti

La gestione oggetti è integrata nel gioco quotidiano.

**Cosa abilita**  
Oggetti, equipaggiamento e uso contestuale sostengono identità del personaggio, progressione e gioco economico.

**Esempio**  
Un personaggio cambia equipaggiamento prima di una missione, di un’esplorazione o di una scena di conflitto. La scelta diventa parte del racconto.

### Shop e banca

La parte economica copre acquisti, vendite e movimentazioni.

**Risultato**  
La land può progettare dinamiche economiche credibili e continuative senza affidarsi a strumenti esterni.

### Lavori

I lavori offrono routine e crescita.

**Perché conta**  
Non tutte le attività di una community vivono di grandi eventi. I lavori danno ritmo, continuità e piccole motivazioni ricorrenti.

### Gilde

Le gilde includono candidature, ruoli, requisiti, annunci, eventi e log.

**Impatto pratico**  
I gruppi diventano veri motori organizzativi della land, non semplici etichette.

**Esempio**  
Una gilda può gestire onboarding interno, ruoli, eventi e comunicazioni con una struttura chiara.

### Fazioni

Le fazioni introducono una dimensione macro:

1. appartenenza;
2. relazioni tra gruppi;
3. strategie;
4. conflitti politici e sociali.

**Risultato**  
La land può sostenere tensioni di lungo periodo e dinamiche collettive più profonde.

### Meteo

Il meteo aggiunge atmosfera e coerenza di mondo.

**Cosa abilita**  
L’ambiente può diventare elemento narrativo: una pioggia persistente, una stagione difficile o un clima ostile possono influenzare scene, tono e decisioni.

### Notifiche

Le notifiche aiutano il giocatore a non perdere passaggi importanti.

**Risultato**  
Meno dispersione, più continuità e maggiore capacità di rientrare nel flusso della community.

---

## 5) Narrativa, quest, eventi e conflitti

Logeon v1.0.0 non si limita a fornire chat e pagine. Offre strumenti per organizzare la narrazione nel tempo.

Questa è una delle aree più importanti per una land che vuole evitare improvvisazione continua, perdita di memoria narrativa e conflitti gestionali.

### Narrativa avanzata

Eventi, NPC, tag e stati narrativi consentono una regia più profonda.

**Impatto pratico**  
Lo staff può orchestrare trame complesse mantenendo coerenza, tracciabilità e memoria degli sviluppi.

**Esempio**  
Un evento introduce uno stato narrativo in una zona. Le scene successive riflettono quell’evoluzione in modo ordinato e riconoscibile.

### Quest

Le quest coprono definizione, progressione, esiti e ricompense.

**Cosa cambia**  
Si passa da missioni improvvisate a percorsi narrativi più stabili e verificabili.

**Esempio**  
Una quest pubblica si sviluppa in più fasi, con condizioni d’accesso, step intermedi e chiusura con premi mirati.

### Conflitti

Il sistema conflitti aiuta a gestire tensioni, proposte, azioni e risoluzioni in modo ordinato.

**Perché è importante**  
Le scene critiche sono quelle in cui aumentano più facilmente incomprensioni e contestazioni. Uno strumento dedicato riduce caos e ambiguità.

### Eventi di sistema

Gli eventi di sistema sono pensati per contenuti su ampia scala:

1. stagioni;
2. campagne community;
3. ricorrenze narrative;
4. eventi globali;
5. iniziative con partecipazione strutturata.

**Risultato**  
Lo staff può lanciare contenuti collettivi senza dover gestire tutto manualmente o tramite canali esterni.

---

## 6) Area Staff/Admin: dalla moderazione alla direzione strategica

La v1.0.0 fornisce una regia completa per chi guida la land.

L’area Staff/Admin non serve solo a “sistemare problemi”: serve a prendere decisioni, distribuire responsabilità e mantenere coerenza nel tempo.

### Dashboard amministrativa

La dashboard è il punto di controllo iniziale:

1. quadro sintetico;
2. accessi rapidi;
3. orientamento operativo.

**Risultato**  
Lo staff capisce più velocemente dove intervenire e quali aree richiedono attenzione.

### Gestione utenti e personaggi

Lo staff può:

1. cercare;
2. filtrare;
3. intervenire;
4. approvare richieste;
5. applicare misure di moderazione.

**Impatto pratico**  
Le situazioni sensibili vengono gestite con tempi più rapidi e meno frizioni.

### Ruoli e governance

La governance distingue i ruoli operativi principali:

1. Superuser;
2. Admin;
3. Moderatore;
4. Master;
5. Utente.

La gerarchia dei permessi permette di distribuire responsabilità senza creare zone grigie decisionali. Dove serve, possono essere usate etichette organizzative interne per descrivere responsabilità specifiche, come creatore, gestore, sviluppatore o grafico.

**Esempio**  
Un team con più figure operative può collaborare senza centralizzare ogni decisione su una sola persona.

### Contenuti, mondo e comunicazione

L’admin controlla:

1. mappe e location;
2. media;
3. forum e categorie;
4. documentazione ufficiale;
5. pagine informative pubbliche.

**Risultato**  
La land mantiene coerenza editoriale e narrativa anche quando più persone contribuiscono ai contenuti.

### Economia e oggetti

La gestione amministrativa copre:

1. oggetti;
2. categorie;
3. rarità;
4. slot e regole equip;
5. negozi e inventari;
6. valute.

**Impatto pratico**  
Progressione e bilanciamento possono essere calibrati in modo ordinato, senza interventi destrutturati.

### Narrativa e sistemi complessi

Lo staff può governare:

1. quest;
2. eventi narrativi;
3. NPC;
4. stati;
5. conflitti;
6. eventi di sistema;
7. lifecycle dei personaggi.

**Risultato**  
La gestione narrativa diventa pianificabile e tracciabile, non solo reattiva.

### Log e tracciabilità

Le aree log danno memoria operativa:

1. cosa è successo;
2. chi ha fatto cosa;
3. quando è successo;
4. dove intervenire.

**Perché conta**  
La tracciabilità rende più eque le decisioni dello staff e riduce il rischio di conflitti interpretativi.

---

## 7) Comunicazione email: relazione continua con la community

Logeon integra una suite email completa:

1. template;
2. liste;
3. campagne;
4. invii test;
5. programmazione;
6. monitoraggio esiti.

### Perché conta davvero

In molte land la comunicazione si disperde tra canali diversi: forum, chat esterne, messaggi privati, social e annunci manuali.

Con una suite email integrata puoi centralizzare:

1. annunci importanti;
2. promemoria eventi;
3. aggiornamenti periodici;
4. messaggi di servizio;
5. comunicazioni segmentate.

**Impatto pratico**  
La community resta più informata, più coinvolta e più stabile.

**Esempio**  
Prepari una newsletter mensile con eventi, risultati e teaser narrativi. Segmenti il pubblico con liste dedicate e valuti l’efficacia dagli esiti di invio.

---

## 8) Installazione e primo avvio

Il percorso iniziale è guidato e progressivo:

1. configurazione;
2. verifica;
3. inizializzazione;
4. creazione account principale;
5. chiusura setup.

**Risultato**  
Si riducono i blocchi in avvio progetto e si parte con una base ordinata.

L’installazione non è solo un passaggio tecnico: è il momento in cui la land passa da progetto teorico a piattaforma attiva. Un setup guidato riduce il rischio di configurazioni incomplete, database non inizializzato o ambiente lasciato in stato incoerente.

---

## 9) Personalizzazione della land

La personalizzazione non riguarda solo colori o testi.

Con Logeon v1.0.0 puoi modellare:

1. tono pubblico;
2. struttura informativa;
3. gerarchie staff;
4. atmosfera narrativa;
5. gestione economica;
6. comunicazione periodica;
7. peso di gilde, fazioni, quest ed eventi;
8. ritmo quotidiano del gioco.

**Impatto pratico**  
Non usi un contenitore neutro: costruisci un ambiente riconoscibile, coerente con l’identità della tua community.

Una land più politica, una più esplorativa, una più sociale o una più orientata al conflitto possono partire dalla stessa base, ma configurare priorità e strumenti in modo diverso.

---

## 10) Scenari di utilizzo ad alto valore

### Scenario A - Land nuova, team piccolo

**Obiettivo**  
Andare online in tempi brevi senza sacrificare qualità e controllo.

**Come aiuta Logeon**

1. onboarding strutturato;
2. pannello admin unico;
3. strumenti narrativi già integrati;
4. comunicazione utenti già pronta.

**Risultato**  
Meno tempo su problemi operativi, più tempo su gioco, ambientazione e community.

### Scenario B - Land attiva che vuole crescere

**Obiettivo**  
Passare da gestione artigianale a gestione scalabile.

**Come aiuta Logeon**

1. ruoli e governance più chiari;
2. log più completi;
3. strumenti quest/eventi/conflitti più ordinati;
4. comunicazione email per engagement regolare.

**Risultato**  
Crescita più sostenibile e minore sovraccarico sugli stessi pochi staffer.

### Scenario C - Progetto narrativo ambizioso

**Obiettivo**  
Mantenere coerenza su trame lunghe, molti partecipanti e molte informazioni.

**Come aiuta Logeon**

1. eventi e stati narrativi;
2. NPC gestibili in modo sistematico;
3. quest multistep;
4. archivi consultabili;
5. log e tracciabilità.

**Risultato**  
Memoria narrativa più forte e qualità percepita più alta.

---

## 11) Percorsi rapidi consigliati

### Percorso “Primo mese” per founder

**Settimana 1**

1. installazione;
2. configurazione pubblica;
3. ruoli staff base.

**Settimana 2**

1. mappe e location;
2. regolamento, storyboard e come giocare;
3. prime gilde e fazioni.

**Settimana 3**

1. setup economia, oggetti e negozi;
2. prime quest;
3. primo evento narrativo.

**Settimana 4**

1. campagna email community;
2. revisione log;
3. ottimizzazione governance.

### Percorso “Entrata rapida” per nuovo giocatore

1. Leggo ambientazione e regole.
2. Mi registro.
3. Creo o scelgo il personaggio.
4. Entro in una location.
5. Avvio o raggiungo una scena.
6. Uso messaggi privati e forum per integrarmi nella community.

---

## 12) Moduli opzionali non inclusi nella release base

Questi moduli sono estensioni scaricabili a parte. Non fanno parte del pacchetto standard v1.0.0.

### 1. `logeon.abilities-spells`

Introduce un sistema dedicato ad abilità e incantesimi, utile per land con forte componente magica o class-based.

### 2. `logeon.advanced-items`

Espande il dominio oggetti con logiche più ricche, adatto a economie e inventari molto dettagliati.

### 3. `logeon.advanced-narrative-classification`

Aggiunge classificazioni narrative avanzate per organizzare meglio contenuti, eventi e contesti.

### 4. `logeon.archetype-attributes`

Approfondisce il legame tra archetipi e attributi, aumentando la specializzazione dei personaggi.

### 5. `logeon.combat-admin-tools`

Fornisce strumenti extra di regia e controllo per lo staff durante la gestione dei combattimenti.

### 6. `logeon.combat-ai`

Aggiunge componenti di supporto automatizzato al dominio combattimento, per scenari più evoluti.

### 7. `logeon.combat-coordination`

Migliora la coordinazione delle azioni tra più partecipanti nelle fasi di conflitto.

### 8. `logeon.combat-environment`

Introduce variabili ambientali nel combattimento, rendendo il contesto più influente sulle dinamiche di scena.

### 9. `logeon.crafting-production`

Apre a sistemi di crafting e produzione, utili per economie orientate a creazione e filiere interne.

### 10. `logeon.economy`

Espansione economica avanzata per chi vuole una gestione monetaria e di mercato oltre il livello base.

### 11. `logeon.narrative-combat`

Integra in modo più stretto strumenti narrativi e meccaniche di combattimento.

### 12. `logeon.narrative-states`

Estende gli stati narrativi con funzionalità più avanzate su condizioni, transizioni ed effetti.

### 13. `logeon.polls`

Aggiunge sondaggi interni alla community per consultazioni rapide e processi decisionali partecipati.

---

## 13) Conclusione

Logeon v1.0.0 non è solo una somma di funzioni: è una base operativa completa per creare, avviare e governare una community play-by-chat.

La release copre i bisogni fondamentali di una land moderna:

1. accoglienza dei nuovi utenti;
2. gioco quotidiano;
3. strumenti narrativi;
4. economia e progressione;
5. organizzazioni;
6. moderazione;
7. comunicazione;
8. controllo amministrativo.

Il valore principale sta nella coerenza dell’ecosistema. Area pubblica, area gioco, strumenti staff, comunicazione e moduli lavorano insieme per rendere la community più leggibile, stabile e sostenibile.

Da qui in avanti, la crescita può concentrarsi su specializzazioni e moduli avanzati, senza dover ricostruire le fondamenta.

Logeon v1.0.0 chiude la fase evolutiva iniziale e apre una fase di consolidamento e crescita strutturata.
