# Guida completa alla creazione e gestione delle Quest

> **A chi è rivolto questo documento**
> Staff e amministratori che vogliono creare, configurare e gestire quest su Logeon.
> Il documento copre sia il pannello amministrativo che gli strumenti di gioco in-game.

---

## Indice

1. [Concetti fondamentali](#1-concetti-fondamentali)
2. [Il flusso di vita di una quest](#2-il-flusso-di-vita-di-una-quest)
3. [Creare la Definizione](#3-creare-la-definizione)
4. [Aggiungere gli Step](#4-aggiungere-gli-step)
5. [Configurare le Condizioni](#5-configurare-le-condizioni)
6. [Configurare gli Esiti](#6-configurare-gli-esiti)
7. [Collegare eventi (Link eventi)](#7-collegare-eventi-link-eventi)
8. [Assegnare le Istanze](#8-assegnare-le-istanze)
9. [Gestire il progresso in-game](#9-gestire-il-progresso-in-game)
10. [Scrivere la Chiusura](#10-scrivere-la-chiusura)
11. [Assegnare le Ricompense](#11-assegnare-le-ricompense)
12. [Il punto di vista del giocatore](#12-il-punto-di-vista-del-giocatore)
13. [Esempio pratico guidato](#13-esempio-pratico-guidato)
14. [Riferimento rapido dei valori ammessi](#14-riferimento-rapido-dei-valori-ammessi)

---

## 1. Concetti fondamentali

Il sistema quest è organizzato in **tre livelli distinti** che è importante tenere separati nella mente:

| Livello | Nome | Cosa rappresenta |
|---------|------|-----------------|
| 1 | **Definizione** | Il "blueprint" della quest — il modello master creato in admin |
| 2 | **Istanza** | Una copia attiva della definizione assegnata a un personaggio, fazione o gilda |
| 3 | **Storico / Chiusura** | Il risultato finale documentato, visibile al giocatore nel suo archivio |

**Analogia utile:** la definizione è la ricetta, l'istanza è il piatto che hai cucinato per una persona specifica, la chiusura è il giudizio scritto sul risultato.

### Chi fa cosa

| Azione | Staff | Giocatore |
|--------|-------|-----------|
| Creare/pubblicare definizioni | ✅ | ❌ |
| Aggiungere step, condizioni, esiti | ✅ | ❌ |
| Assegnare istanze | ✅ | ❌ (salvo `manual_join`) |
| Aderire a una quest aperta | ✅ | ✅ |
| Abbandonare la partecipazione | ✅ | ✅ |
| Confermare step manuali | ✅ | ❌ |
| Forzare lo stato di un'istanza | ✅ | ❌ |
| Scrivere e finalizzare chiusure | ✅ | ❌ |
| Assegnare ricompense | ✅ | ❌ |
| Vedere la propria storia quest | ✅ | ✅ |

---

## 2. Il flusso di vita di una quest

```
[ADMIN]
   │
   ▼
Definizione (status: draft)
   │  configura step, condizioni, esiti
   ▼
Definizione (status: published)
   │
   ├─── availability_type: manual_join ──► il giocatore la vede e si iscrive autonomamente
   ├─── availability_type: staff_assign ──► lo staff la assegna manualmente
   └─── availability_type: auto_join ────► il sistema la assegna all'evento opportuno
   │
   ▼
Istanza creata (status: available oppure active)
   │
   ▼
Progressione step per step
   │  (staff conferma step manuali, il sistema avanza step automatici)
   ▼
Istanza terminale (completed / failed / cancelled / expired)
   │
   ▼
Staff scrive la Chiusura (closure_type, summary_public, ricompense)
   │
   ▼
Giocatore vede il risultato nel proprio Archivio quest
```

---

## 3. Creare la Definizione

**Percorso admin:** Area Amministrativa → Quests → pulsante "Nuova quest"

La definizione è il cuore del sistema. Finché è in `draft` non ha effetti nel gioco. Pubblica solo quando sei sicuro della configurazione di base.

### Sezione Identificazione

| Campo | Descrizione | Note |
|-------|-------------|------|
| **Slug** | Identificatore univoco interno (es. `missione_del_tramonto`) | Usa lettere minuscole, underscore, niente spazi. **Non modificare dopo la pubblicazione** — il sistema usa lo slug per riferimenti interni |
| **Titolo** | Nome visibile ai giocatori | Puoi cambiarlo liberamente anche dopo la pubblicazione |

### Sezione Configurazione

| Campo | Valori | Descrizione |
|-------|--------|-------------|
| **Tipo quest** | `personal`, `faction`, `guild`, `world`, `storyline`, `event` | Categorizzazione narrativa. Influenza il gruppo di assegnatari predefinito e le regole di visibilità |
| **Stato** | `draft`, `published`, `archived` | `draft` = non visibile; `published` = attiva; `archived` = disattivata ma conservata |
| **Visibilità** | `public`, `private`, `staff_only`, `hidden` | Controlla chi vede la quest nelle liste, indipendente dallo stato |
| **Disponibilità** | `manual_join`, `staff_assign`, `auto_join` | Come i giocatori entrano nella quest (vedi sotto) |
| **Intensità narrativa** | `CHILL`, `SOFT`, `STANDARD`, `HIGH`, `CRITICAL` | Indicatore di pressione narrativa, non di difficoltà meccanica. Non impatta le meccaniche automaticamente |
| **Visibilità intensità** | `visible`, `hidden` | Se `hidden`, il livello non viene mostrato ai giocatori |
| **Ordine** | numero intero | Posizione nelle liste ordinate; usa valori a gap (10, 20, 30…) per inserimenti futuri |

#### Disponibilità — Dettaglio

- **`manual_join`** — la quest è visibile ai giocatori qualificati che possono iscriversi autonomamente cliccando "Partecipa" nel pannello di gioco. Usa per quest di gilda aperte, eventi pubblici.
- **`staff_assign`** — solo lo staff può creare istanze. Nessun pulsante "Partecipa" appare ai giocatori. Usa per trame personali, missioni segrete.
- **`auto_join`** — il motore crea automaticamente l'istanza al verificarsi di un evento (ingresso in fazione, cambio fase narrativa, ecc.). Richiede configurazione di un trigger.

#### Visibilità — Dettaglio

- **`public`** — visibile a tutti i giocatori autenticati (se `published`)
- **`private`** — visibile solo all'assegnatario dell'istanza
- **`staff_only`** — visibile solo a staff e amministratori
- **`hidden`** — non appare mai nelle liste; accessibile solo via ID diretto

### Sezione Ambito

Definisce il "perimetro" geografico o organizzativo della quest.

| Campo | Valori | Descrizione |
|-------|--------|-------------|
| **Tipo ambito** | `world`, `character`, `faction`, `guild`, `map`, `location` | `world` = nessun ambito specifico (quest globale). Gli altri restringono la visibilità a chi appartiene a quell'entità |
| **Target ambito** | ricerca guidata | Il personaggio/fazione/gilda/mappa/luogo specifico. Lascia vuoto se `world` |

### Sezione Contenuto

| Campo | Descrizione | Suggerimento |
|-------|-------------|--------------|
| **Sommario** | Testo breve per liste e anteprime | Scrivi meno di 200 parole. Il giocatore lo vede prima di aprire i dettagli |
| **Descrizione** | Testo completo visibile quando il giocatore apre il dettaglio | Supporta formattazione ricca (TipTap). Puoi includere lore, obiettivi generali, atmosfera |
| **Tag narrativi** | checkbox multipli | Massimo 8 tag. Usati per filtrare e raggruppare nelle interfacce. Scegli tag già esistenti quando possibile |

> **Consiglio:** Pubblica la definizione solo dopo aver configurato almeno uno step. Una quest senza step è tecnicamente valida ma non guida il giocatore.

---

## 4. Aggiungere gli Step

**Percorso:** Dettaglio quest → pulsante "Step"

Gli step sono i passaggi che il giocatore (o il sistema) deve completare nell'ordine stabilito. Ogni step ha un proprio stato di avanzamento per ogni istanza.

### Campi dello step

| Campo | Valori | Descrizione |
|-------|--------|-------------|
| **Identificatore step** (`step_key`) | testo libero, es. `step_intro` | Slug interno usato dal motore. Non visibile ai giocatori. Deve essere unico all'interno della quest |
| **Titolo** | testo | Mostrato allo staff e nei log. Può essere visibile al giocatore a seconda della configurazione |
| **Tipo** | `action`, `dialogue`, `checkpoint`, `system` | Classificazione narrativa: `action` = interazione attiva del giocatore; `dialogue` = scena narrativa; `checkpoint` = passaggio verificato automaticamente; `system` = gestito dal motore senza intervento |
| **Ordine** | numero intero | Sequenza di esecuzione. Usa valori con gap (10, 20, 30…) per inserire step in mezzo in seguito |
| **Opzionale** | `No` / `Sì` | Se `Sì`, il fallimento di questo step non blocca il completamento della quest. Utile per step bonus |
| **Descrizione** | testo ricco | Istruzioni narrative o contestuali. Supporta TipTap |

### Ordine e salvataggio

- Usa il pulsante **"Salva ordine"** nel footer della modale dopo aver modificato i numeri di ordine — salva tutti gli ordini in blocco.
- Il pulsante **"Reset"** svuota il form senza modificare dati esistenti.
- Per **modificare** uno step esistente, cliccaci sopra dall'elenco: il form si precompila.
- Per **eliminare** uno step, usa il pulsante di eliminazione nella riga dell'elenco (attenzione: elimina anche le istanze di step associate).

### Raccomandazioni pratiche

- Mantieni gli step in ordine logico narrativo: step 10, 20, 30 lascia spazio per inserire uno step 15 in seguito.
- Un solo step "checkpoint" alla fine è utile per le quest che si completano in un'unica sessione.
- Gli step `system` non richiedono interazione e avanzano automaticamente quando il motore rileva la condizione.

---

## 5. Configurare le Condizioni

**Percorso:** Dettaglio quest → pulsante "Condizioni"

Le condizioni definiscono **quando** uno step o la quest si considera soddisfatta. Senza condizioni, uno step viene confermato manualmente dallo staff o non avanza mai.

### Dove si applica

Una condizione può essere collegata a:
- **La quest intera** (lascia "Condizione di quest" nel campo Step) — verificata a livello globale
- **Uno step specifico** — verificata solo nel contesto di quello step

### Tipo trigger

Il tipo trigger descrive **quale evento del sistema** genera il segnale di verifica:

| Tipo | Descrizione |
|------|-------------|
| `manual_staff` | Lo staff conferma manualmente. Non c'è verifica automatica |
| `narrative.event.created` | Si verifica quando viene creato un evento narrativo di un certo tipo |
| `system_event.status_changed` | Si verifica quando un evento di sistema cambia stato |
| `faction.membership.changed` | Si verifica quando cambia la membership di un personaggio in una fazione |
| `lifecycle.phase.entered` | Si verifica quando il ciclo di vita del gioco entra in una certa fase |
| `presence.position_changed` | Si verifica quando un personaggio cambia posizione/luogo |
| `quest_lifecycle` | Si verifica su eventi interni del ciclo vita di un'altra quest |

> Per la maggior parte delle quest narrative tradizionali, usa **`manual_staff`**: lo staff conferma a mano il completamento di ogni step dopo che è avvenuto il roleplay.

### Come viene valutata

| Campo | Valori | Descrizione |
|-------|--------|-------------|
| **Operatore** | `eq`, `ne`, `in`, `not_in`, `gt`, `gte`, `lt`, `lte`, `contains` | Come confrontare il valore del payload con quello atteso |
| **Modalità** | `all_required`, `any_required`, `blocking`, `optional` | `all_required` = tutte le condizioni devono essere vere; `any_required` = basta una; `blocking` = il fallimento blocca l'avanzamento; `optional` = registrata ma non bloccante |

### Campo e valore del payload

Usati per condizioni automatiche (non `manual_staff`):

| Campo | Descrizione |
|-------|-------------|
| **Campo payload** | Quale campo dell'evento ispezionare (es. `status`, `event_type`, `map_id`) |
| **Valore payload** | Il valore atteso nel campo scelto |

Per operatori `in` e `not_in`, inserisci valori separati da virgola nel campo avanzato.

### Attiva

Una condizione con `Attiva = No` esiste nella configurazione ma viene ignorata dal motore. Utile per "spegnere" temporaneamente una condizione senza eliminarla.

---

## 6. Configurare gli Esiti

**Percorso:** Dettaglio quest → pulsante "Esiti"

Gli esiti definiscono **cosa succede** quando uno step o la quest raggiunge un certo stato. Sono automatici (se `requires_staff_confirmation = No`) o entrano in coda di approvazione (se `= Sì`).

### Quando scatta (Trigger)

| Trigger | Descrizione |
|---------|-------------|
| `step_completed` | Si attiva quando uno step viene completato |
| `quest_completed` | Si attiva quando tutta la quest viene completata |
| `quest_failed` | Si attiva quando la quest fallisce |
| `manual_staff` | Attivato esplicitamente dallo staff, non automaticamente |

Se più esiti condividono lo stesso trigger, il campo **Ordine** determina la sequenza di esecuzione.

### Cosa produce (Tipo esito)

| Tipo esito | Descrizione |
|------------|-------------|
| `log_progress` | Registra un log interno di progressione (invisibile ai giocatori) |
| `notify` | Invia una notifica al personaggio/fazione assegnatari |
| `create_narrative_event` | Crea automaticamente un evento nel registro cronache |
| `unlock_quest` | Sblocca un'altra quest (rende disponibile un'istanza locked) |
| `complete_quest` | Completa forzatamente un'altra quest |
| `fail_quest` | Fallisce forzatamente un'altra quest |
| `apply_narrative_state` | Applica uno stato narrativo al personaggio/entità |
| `remove_narrative_state` | Rimuove uno stato narrativo |

### Visibilità

| Valore | Chi vede l'esito |
|--------|-----------------|
| `hidden` | Nessuno (solo log interni) |
| `public` | Tutti i giocatori |
| `private` | Solo il personaggio/entità assegnataria |
| `staff_only` | Solo staff e amministratori |

### Parametri per tipo

- **Quest target** — necessario per `unlock_quest` e `complete_quest`: cerca la definizione di destinazione
- **Tipo evento narrativo** — necessario per `create_narrative_event` (es. `quest_update`, `quest_completed`)
- **Titolo evento narrativo** — titolo della cronaca generata automaticamente
- **Messaggio / causale** — testo incluso nell'evento o nella notifica (supporta TipTap)
- **Stato narrativo** — necessario per `apply_narrative_state` e `remove_narrative_state`
- **Intensità / Durata** — per stati narrativi con durata e forza definite

### Conferma staff

Se **`Sì`**, l'esito viene messo in una coda e non ha effetto fino a che uno staff member non lo approva esplicitamente. Utile per ricompense importanti o azioni narrative delicate.

---

## 7. Collegare eventi (Link eventi)

**Percorso:** Dettaglio quest → pulsante "Link eventi"

I link permettono di collegare una quest a eventi narrativi o di sistema esistenti, creando un grafo di relazioni consultabile dallo staff.

Non hanno effetti meccanici automatici: servono come documentazione strutturata e possono essere usati da future feature di ricerca/filtraggio.

### Tipo entità

| Valore | Descrizione |
|--------|-------------|
| `narrative_event` | Un evento nel registro cronache |
| `system_event` | Un evento del motore (elezioni, guerre, ecc.) |

### Tipo link

| Valore | Significato |
|--------|-------------|
| `contextualized_by` | La quest prende contesto narrativo dall'evento (es. "questa missione nasce dall'evento X") |
| `triggered_by` | L'evento ha causato o innescato la quest |
| `source_of` | La quest ha generato o è fonte dell'evento |
| `related_to` | Relazione generica, per affiliazioni narrative non classificabili |

---

## 8. Assegnare le Istanze

**Percorso:** Dettaglio quest → pulsante "Istanze"

Un'istanza è la "copia in esecuzione" della quest assegnata a un'entità concreta. Creare una definizione non genera automaticamente istanze: bisogna assegnarle.

> **Eccezione:** se `availability_type = manual_join`, i giocatori stessi creano la propria istanza cliccando "Partecipa" in-game. Se `auto_join`, il sistema crea l'istanza automaticamente all'evento trigger.

### Assegna nuova istanza

| Campo | Valori | Descrizione |
|-------|--------|-------------|
| **Assegnatario** | `character`, `faction`, `guild`, `world` | Il tipo di entità che riceve la quest |
| **Target** | ricerca guidata | Il personaggio, la fazione o la gilda specifica. Non richiesto per `world` |
| **Stato iniziale** | `available`, `active` | `available` = visibile ma non ancora avviata; `active` = già in corso dal momento dell'assegnazione |
| **Scadenza** | data e ora | Lascia vuoto per nessuna scadenza. Il motore passerà automaticamente l'istanza a `expired` alla scadenza |
| **Intensità istanza** | `CHILL`, `SOFT`, `STANDARD`, `HIGH`, `CRITICAL`, o vuoto | Sovrascrive l'intensità della definizione per questa sola istanza. Lascia "Eredita definizione" per usare il valore della definizione |
| **Note assegnazione** | testo libero | Note interne staff. Non visibili al giocatore |

### Stato dell'istanza

| Status | Descrizione |
|--------|-------------|
| `locked` | Prerequisiti non soddisfatti; il giocatore non può vederla né parteciparvi |
| `available` | Visibile al giocatore; può aderire (se `manual_join`) |
| `active` | In corso; step in progressione |
| `completed` | Terminata con successo |
| `failed` | Fallita |
| `cancelled` | Annullata dallo staff |
| `expired` | Scaduta per tempo |

### Azione rapida

Il pannello "Modifica istanza selezionata" nella modale istanze permette di:

- **Impostare lo stato direttamente** (senza passare per la logica di step o esiti) — usalo con cautela: bypassa completamente il motore
- **Impostare lo stato di uno step specifico** — utile per correggere lo stato di un singolo step senza influenzare il resto

**Flusso consigliato per la modifica:** seleziona l'istanza dall'elenco → il pannello si precompila → modifica → salva.

---

## 9. Gestire il progresso in-game

**Percorso in-game:** Narrativa (navbar) → Quests → tab "Staff" (visibile solo a staff e admin)

Il pannello quest in-game mostra le istanze attive e permette azioni rapide senza uscire dall'interfaccia di gioco.

### Cosa si può fare dal pannello in-game

| Azione | Descrizione |
|--------|-------------|
| **Conferma step** | Marca come completato uno step che richiede conferma manuale staff |
| **Forza progresso** | Avanza forzatamente l'istanza al prossimo step/branch, ignorando condizioni non soddisfatte |
| **Cambia stato istanza** | Imposta direttamente `completed`, `failed`, `cancelled`, ecc. |
| **Cambia intensità** | Sovrascrive l'intensità per quell'istanza |

### Quando usare "Forza progresso"

Il force progress è uno strumento di emergenza per situazioni in cui:
- Un trigger automatico non ha scattato come previsto
- Il roleplay è avanzato narrativamente ma il sistema non lo riflette
- Si vuole saltare uno step non più rilevante

Non abusarne: bypassa le condizioni e può rendere la storia quest incoerente con i log.

---

## 10. Scrivere la Chiusura

**Percorso:** Dettaglio quest (admin) → pulsante "Chiusure"

La chiusura è il documento finale che sigilla un'istanza terminale. È obbligatoria per rendere visibile il risultato al giocatore nel suo archivio.

### Selezione istanza

Cerca l'istanza tramite il campo guidato. Puoi filtrare per tipo di chiusura.

### Sezione Esito

| Campo | Valori | Descrizione |
|-------|--------|-------------|
| **Stato finale** | `completed`, `failed`, `cancelled`, `expired` | Stato tecnico dell'istanza dopo la chiusura. Deve corrispondere allo stato attuale dell'istanza |
| **Tipo chiusura** | `success`, `partial_success`, `failure`, `cancelled`, `unresolved` | Interpretazione narrativa della conclusione. **Indipendente dallo stato tecnico**: una quest `failed` può avere tipo `partial_success` se il personaggio ha comunque ottenuto qualcosa |
| **Visibile al player** | `Sì` / `No` | Se `Sì`, il giocatore vede il summary pubblico nel suo archivio |
| **Etichetta esito** | testo breve | Mostrata nello storico (es. "Missione compiuta", "Ritirata tattica", "Fallimento con onore") |

#### Tipo chiusura — Quando usarlo

| Tipo | Quando |
|------|--------|
| `success` | La quest è andata esattamente come pianificato |
| `partial_success` | Il giocatore ha completato alcuni obiettivi ma non tutti, oppure ha trovato una via alternativa |
| `failure` | Il giocatore non ha raggiunto nessun obiettivo significativo |
| `cancelled` | La quest è stata annullata dallo staff per motivi narrativi o tecnici |
| `unresolved` | La quest si è chiusa senza un esito narrativo definito (raro, usato per interruzioni tecniche) |

### Sezione Testi

| Campo | Stile | Visibilità |
|-------|-------|------------|
| **Summary pubblico** | Terza persona, sintetico, max 2-3 paragrafi | Giocatore nel suo archivio (se `player_visible = Sì`) |
| **Summary privato** | Tono interno staff, può essere più dettagliato | Solo staff — non visibile ai giocatori in nessun caso |
| **Note staff** | Annotazioni operative (follow-up, inconsistenze, ecc.) | Solo staff |

> **Stile del summary pubblico:** scrivi in terza persona come se fosse una cronaca storica. "Il personaggio ha scoperto la verità sulla scomparsa e ha consegnato le prove alle autorità." Non "Hai scoperto la verità."

---

## 11. Assegnare le Ricompense

**Percorso:** Dettaglio quest (admin) → pulsante "Ricompense"

Le ricompense vengono assegnate dopo o durante la chiusura. Possono anche essere assegnate prima della chiusura formale se il contesto lo richiede.

### Assegna ricompensa

| Campo | Valori | Descrizione |
|-------|--------|-------------|
| **Istanza quest** | ricerca guidata | L'istanza a cui è collegata la ricompensa |
| **Destinatario** | ricerca guidata | Il personaggio che riceve materialmente la ricompensa |
| **Tipo reward** | `experience`, `item` | Seleziona prima il tipo, poi compila i campi specifici |
| **Visibilità** | `public`, `player_private`, `staff_only` | `player_private` = solo il destinatario la vede nel suo archivio; `staff_only` = mai visibile ai giocatori |
| **Oggetto** | ricerca guidata | Richiesto solo per tipo `item` |
| **Quantità** | numero | Quantità dell'oggetto (solo per `item`) |
| **Esperienza** | numero decimale | Quantità di XP (solo per `experience`) |

### Storico ricompense

L'elenco sotto il form mostra tutte le ricompense già assegnate per la quest corrente, con opzioni di eliminazione.

---

## 12. Il punto di vista del giocatore

### Dove il giocatore vede le quest

1. **Pannello Quests** (Narrativa → Quests in navbar) — lista le quest `available` e `active` dell'utente
2. **Archivio quest** (profilo → "Archivi di giocata" → oppure `/game/quests/history`) — lista le quest concluse con filtri per stato, periodo e ricerca testuale

### Cosa vede il giocatore

Il giocatore vede **solo**:
- Definizioni con `visibility = public` o `private` (se è lui l'assegnatario)
- Istanze in cui è coinvolto (come personaggio, o come membro di fazione/gilda assegnataria)
- Chiusure con `player_visible = Sì`
- Summary pubblico della chiusura (non il summary privato né le note staff)
- Ricompense con visibilità `public` o `player_private` (solo le proprie)
- L'intensità narrativa solo se `intensity_visibility = visible`

### Aderire e abbandonare

Per quest con `availability_type = manual_join`, il giocatore può:
- Cliccare **"Partecipa"** nella scheda della quest → crea un'istanza `active` a suo nome
- Cliccare **"Abbandona"** → porta l'istanza a `cancelled`

---

## 13. Esempio pratico guidato

L'esempio seguente descrive passo per passo la creazione di una quest personale con tre step, una condizione automatica, un esito di notifica e una chiusura finale.

### Scenario narrativo

> *Un mercante di nome Aldric chiede al personaggio di recuperare un pacco misterioso scomparso durante un trasporto. Lo staff gestirà il roleplay di scoperta; al termine, il personaggio riceverà una ricompensa in oggetti.*

---

### Passo 1 — Crea la Definizione

Vai in **Admin → Quests → Nuova quest** e compila:

```
Slug:               recupero_pacco_aldric
Titolo:             Il Pacco di Aldric
Tipo quest:         personal
Stato:              draft   ← lascia in draft finché non hai configurato tutto
Visibilità:         private  ← solo il personaggio assegnato la vede
Disponibilità:      staff_assign  ← nessun pulsante "Partecipa" compare ai giocatori
Intensità:          STANDARD
Visibilità intensità: visible
Ordine:             10

Sommario:
  Aldric, mercante della città bassa, ti ha contattato per recuperare
  un pacco che è scomparso durante un trasporto verso il porto.

Descrizione:
  Il mercante Aldric si mostra nervoso. "Non è roba che posso descriverti",
  dice, "ma è essenziale che torni indietro. Qualcuno ha intercettato il
  corriere prima che raggiungesse il porto." Ti fornisce l'ultima posizione
  nota del corriere e ti chiede discrezione assoluta.
```

Salva. La quest è ora in bozza.

---

### Passo 2 — Aggiungi gli Step

Vai in **Dettaglio quest → Step** e crea i seguenti tre step:

**Step 1 — Indagine iniziale**
```
Identificatore:  step_indagine
Titolo:          Indagine sulla scomparsa
Tipo:            action
Ordine:          10
Opzionale:       No
Descrizione:     Il personaggio deve raccogliere informazioni sull'ultima
                 posizione del corriere, interrogando testimoni nel porto
                 e nei vicoli della città bassa.
```

**Step 2 — Recupero del pacco**
```
Identificatore:  step_recupero
Titolo:          Recupero del pacco
Tipo:            action
Ordine:          20
Opzionale:       No
Descrizione:     Una volta localizzato il pacco (o chi lo ha rubato),
                 il personaggio deve recuperarlo con qualsiasi mezzo a
                 disposizione.
```

**Step 3 — Consegna ad Aldric**
```
Identificatore:  step_consegna
Titolo:          Consegna ad Aldric
Tipo:            checkpoint
Ordine:          30
Opzionale:       No
Descrizione:     Il personaggio porta il pacco ad Aldric e riceve
                 la conferma della consegna.
```

Clicca **"Salva ordine"** per confermare gli ordini.

---

### Passo 3 — Aggiungi una Condizione allo Step di Consegna

Vai in **Condizioni**, seleziona lo step `step_consegna` e crea:

```
Step:            step_consegna
Tipo trigger:    manual_staff     ← lo staff conferma manualmente la consegna
Attiva:          Sì
Operatore:       eq
Modalità:        all_required
Campo payload:   (lascia vuoto per manual_staff)
Valore payload:  (lascia vuoto per manual_staff)
```

Questo significa che lo step di consegna si considera completato **solo quando uno staff member lo conferma esplicitamente** — non c'è avanzamento automatico.

---

### Passo 4 — Aggiungi un Esito al Completamento della Quest

Vai in **Esiti** e crea:

```
Trigger:               quest_completed
Tipo esito:            create_narrative_event
Ordine:                10
Conferma staff:        No
Visibilità:            private
Tipo evento narrativo: quest_completed
Titolo evento:         Il pacco è stato recuperato
Messaggio:             Aldric ha ritirato il pacco con sollievo, ringraziando
                       il personaggio per la discrezione dimostrata.
```

Questo creerà automaticamente una cronaca nel registro narrativo del personaggio quando la quest viene completata.

---

### Passo 5 — Pubblica la Definizione

Torna alla scheda della quest e cambia lo stato da `draft` a `published`. Salva.

La definizione è ora attiva nel sistema.

---

### Passo 6 — Assegna l'Istanza al Personaggio

Vai in **Dettaglio quest → Istanze** e compila il form "Assegna nuova istanza":

```
Assegnatario:     character
Target:           [cerca il nome del personaggio]
Stato iniziale:   active      ← la quest parte già in corso
Scadenza:         (lascia vuoto)
Intensità:        Eredita definizione
Note:             Contatto avvenuto in gioco il [data]. Aldric ha consegnato
                  le coordinate del corriere.
```

Clicca **Assegna**. L'istanza appare nell'elenco.

---

### Passo 7 — Gestisci il Progresso in-Game

Durante il roleplay, lo staff usa il **pannello quest in-game** (Narrativa → Quests → tab Staff):

1. Quando il personaggio completa l'indagine → **Conferma step** `step_indagine`
2. Quando il personaggio recupera il pacco → **Conferma step** `step_recupero`
3. Quando il personaggio consegna ad Aldric → **Conferma step** `step_consegna`

Al completamento dell'ultimo step, il sistema verifica se tutti gli step obbligatori sono `completed` e può avanzare automaticamente l'istanza a `completed` (o richiedere un ultimo cambio di stato manuale, a seconda della configurazione).

---

### Passo 8 — Scrivi la Chiusura

Vai in **Dettaglio quest → Chiusure**, cerca l'istanza del personaggio e scrivi:

```
Stato finale:     completed
Tipo chiusura:    success
Visibile player:  Sì
Etichetta esito:  Consegna completata

Summary pubblico:
  [Nome personaggio] ha rintracciato il pacco scomparso nei magazzini
  del porto, scoprendo che era stato intercettato da un gruppo di
  contrabbandieri. Dopo aver recuperato il carico, lo ha consegnato
  al mercante Aldric che lo attendeva con discreta impazienza.

Summary privato:
  Il pacco conteneva documentazione finanziaria sensibile legata a
  un debito contratto da Aldric con una gilda criminale. Potenziale
  follow-up narrativo: la gilda potrebbe cercare ritorsioni.

Note staff:
  Roleplay svolto nelle sessioni del [data]. Nessuna complicazione tecnica.
  Personaggio ha gestito la situazione in modo eccellente.
```

Salva il report.

---

### Passo 9 — Assegna la Ricompensa

Vai in **Ricompense**, seleziona l'istanza e assegna:

```
Istanza:          [istanza del personaggio]
Destinatario:     [personaggio]
Tipo reward:      item
Visibilità:       player_private
Oggetto:          [cerca "Borsa di monete" o l'oggetto deciso]
Quantità:         1
```

Salva. Il giocatore vedrà la ricompensa nel suo archivio quest.

---

### Risultato finale

Il giocatore aprendo **Archivio quest → Il Pacco di Aldric** vedrà:
- Etichetta: *"Consegna completata"*
- Summary: il testo pubblico scritto nella chiusura
- Ricompensa: la borsa di monete

---

## 14. Riferimento rapido dei valori ammessi

### Definizione

| Campo | Valori validi |
|-------|--------------|
| `status` | `draft` · `published` · `archived` |
| `visibility` | `public` · `private` · `staff_only` · `hidden` |
| `availability_type` | `manual_join` · `staff_assign` · `auto_join` |
| `scope_type` | `world` · `character` · `faction` · `guild` · `map` · `location` |
| `intensity_level` | `CHILL` · `SOFT` · `STANDARD` · `HIGH` · `CRITICAL` |
| `intensity_visibility` | `visible` · `hidden` |

### Istanza

| Campo | Valori validi |
|-------|--------------|
| `assignee_type` | `character` · `faction` · `guild` · `world` |
| `status` | `locked` · `available` · `active` · `completed` · `failed` · `cancelled` · `expired` |

### Step

| Campo | Valori validi |
|-------|--------------|
| `step_type` | `action` · `dialogue` · `checkpoint` · `system` |
| `progress_status` (istanza step) | `pending` · `active` · `completed` · `failed` · `skipped` · `locked` |

### Condizioni

| Campo | Valori validi |
|-------|--------------|
| `condition_type` | `manual_staff` · `narrative.event.created` · `system_event.status_changed` · `faction.membership.changed` · `lifecycle.phase.entered` · `presence.position_changed` · `quest_lifecycle` |
| `operator` | `eq` · `ne` · `in` · `not_in` · `gt` · `gte` · `lt` · `lte` · `contains` |
| `evaluation_mode` | `all_required` · `any_required` · `blocking` · `optional` |

### Esiti

| Campo | Valori validi |
|-------|--------------|
| `trigger_type` | `step_completed` · `quest_completed` · `quest_failed` · `manual_staff` |
| `outcome_type` | `log_progress` · `notify` · `create_narrative_event` · `unlock_quest` · `complete_quest` · `fail_quest` · `apply_narrative_state` · `remove_narrative_state` |
| `visibility` | `hidden` · `public` · `private` · `staff_only` |

### Chiusura

| Campo | Valori validi |
|-------|--------------|
| `final_status` | `completed` · `failed` · `cancelled` · `expired` |
| `closure_type` | `success` · `partial_success` · `failure` · `cancelled` · `unresolved` |

### Ricompense

| Campo | Valori validi |
|-------|--------------|
| `reward_type` | `experience` · `item` |
| `visibility` | `public` · `player_private` · `staff_only` |

