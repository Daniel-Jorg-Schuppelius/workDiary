---
title: "Import Kimai"
topic: admin.kimai
version: 3
keywords:
    - Kimai
    - importare tempi
    - importare timesheet
    - Kimai CSV
    - API Kimai
    - passare da Kimai
    - riscrivere i tempi
    - riscrittura
    - associazione utenti
    - riscrivere le correzioni
    - import orario
    - Kimai ospitato in proprio
    - consenti indirizzi privati
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.clockify
    - admin.toggl
    - finance.open-times
    - admin.organization-settings
    - admin.scheduler
---

La pagina **Import Kimai** porta in WorkDiary le registrazioni di tempo dello
strumento Kimai – come export CSV caricato oppure direttamente tramite l’API
di Kimai. Se lo desidera, riscrive inoltre in Kimai come timesheet i tempi
registrati in WorkDiary e rimanda a Kimai le correzioni ai tempi importati.
Trova la pagina nel menu di sistema (l’ingranaggio **Sistema** nella barra in
alto) sotto **Plugin** → **Import Kimai**, non appena il plugin è attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce Kimai. Attivazione e impostazioni
  valgono solo per l’organizzazione corrente.
- La pagina e le impostazioni sono riservate agli amministratori.
  L’**Inbox di riconciliazione**, in cui risolve i casi aperti, è accessibile
  anche alla contabilità.
- Per la via CSV basta un export dei timesheet da Kimai.
- Per la via API servono l’indirizzo di un’istanza Kimai 2 e il token API di
  un utente Kimai (in Kimai sotto Profilo → Accesso API). Se devono arrivare i
  tempi di tutte le persone, questo utente ha bisogno in Kimai del permesso
  view_other_timesheet.
- L’istanza Kimai deve essere raggiungibile pubblicamente, oppure abiliti
  un’istanza ospitata in proprio nella Sua rete con **Consenti indirizzi
  privati** (vedi Configurazione).

## Configurazione

Le credenziali si inseriscono nella pagina **Plugin** tramite **Configura**
sulla voce Kimai:

1. **URL di base Kimai**: l’indirizzo con cui apre Kimai nel browser – senza
   /api alla fine.
2. **Consenti indirizzi privati**: solo per un’istanza ospitata in proprio
   nella Sua rete (per esempio 192.168.x.x). Senza questo interruttore
   WorkDiary rifiuta gli indirizzi interni. La modifica viene registrata. Se
   il gestore della Sua installazione ha bloccato questa autorizzazione,
   l’interruttore non ha effetto.
3. **Token API Kimai**: il token di Kimai. Viene memorizzato cifrato; un campo
   vuoto mantiene il valore precedente al salvataggio.
4. **Recuperare i tempi di tutti gli utenti**: attivo (predefinito) se
   l’utente del token può leggere i tempi altrui; altrimenti arrivano solo i
   suoi tempi.
5. **Finestra di sincronizzazione (giorni)**: quanto indietro guarda un
   import API senza periodo (predefinito 30 giorni), anche quello orario.
6. **Acquisisci stato fatturabile**: attivo riprende il contrassegno
   fatturabile da Kimai; disattivato non contrassegna mai come fatturabili i
   tempi importati.
7. **Modalità utente singolo** e **Registra i tempi per l’utente**: solo
   per postazioni singole, vedi sotto. L’utente si sceglie dall’elenco.
8. Per la riscrittura **Attiva la riscrittura**, **ID attività Kimai per le
   ricontabilizzazioni** e facoltativamente **Ritrasferimento immediato dei
   nuovi tempi**; per il ritorno delle correzioni **Riscrivere le
   correzioni**.
9. **Salva**. Con **Verifica connessione** nella finestra di dialogo controlla
   l’accesso. Senza token il plugin segnala la modalità CSV – non è un errore.

## Importare i tempi

**Carica CSV:** esporti i tempi da Kimai in formato CSV (Kimai → Tempi →
Esporta → CSV), selezioni il file nella pagina **Import Kimai** e clicchi su
**Importa**. WorkDiary riconosce le colonne dalla riga d’intestazione, in
tedesco o in inglese – per esempio data, inizio, fine o durata, cliente,
progetto, attività, descrizione, fatturabile, tag ed e-mail. Come separatore
vanno bene sia la virgola sia il punto e virgola, e il file può essere al
massimo di 20 MB. Gli orari valgono come ora locale della Sua
organizzazione.

**Importa direttamente dall'API Kimai:** scelga facoltativamente un periodo
(**Da**, **Fino a**) e clicchi su **Importa da API**. Senza periodo WorkDiary
interroga gli ultimi giorni secondo la finestra di sincronizzazione. L’import
salta i timesheet in corso, senza fine.

**Import orario:** non appena URL di base e token API sono memorizzati,
l’import API viene eseguito anche automaticamente ogni ora – sulla finestra
di sincronizzazione e con riconciliazione delle eliminazioni (vedi sotto). La
frequenza si modifica in **Attività pianificate**, alla voce «Import Kimai».
Senza accesso API non c’è import automatico; i file CSV si caricano sempre
qui.

Dopo un import da questa pagina, la pagina indica quante voci sono state create, saltate o lasciate aperte
nell’inbox, e quante non è stato possibile associare a un utente.

## Associare clienti, progetti e persone

- **Progetti:** l’import non crea clienti né progetti. Una voce viene
  registrata se il suo progetto viene trovato: tramite un’associazione
  memorizzata, nell’import API tramite il numero di progetto di Kimai,
  altrimenti tramite lo stesso nome di progetto presso il cliente
  corrispondente. Se nelle impostazioni dell’organizzazione è attivo
  **Assegna i tempi ai progetti tramite parole chiave**, come ultimo passo
  aiuta una corrispondenza univoca per parola chiave.
- **Inbox di riconciliazione:** tutto il resto si raccoglie lì, raggruppato
  per cliente, progetto e attività. La scheda **Inbox di riconciliazione**
  della pagina mostra il numero di gruppi aperti, **Alla casella** vi porta.
  Lì sceglie il cliente, facoltativamente il cliente finale, e il progetto,
  poi registra il gruppo. L’associazione viene memorizzata; gli import
  successivi registrano senza chiedere.
- **Persone:** ogni tempo appartiene alla persona che lo ha registrato in
  Kimai. L’import CSV usa la colonna e-mail, l’import API l’indirizzo e-mail
  dell’utente Kimai (se manca, il nome utente). WorkDiary confronta l’uno o
  l’altro con l’indirizzo e-mail degli utenti attivi; una scelta memorizzata
  nell’inbox per un nome utente resta valida. Senza corrispondenza nasce nell’inbox un caso «Utente
  sconosciuto» o «Voce senza segnale utente», invece di far finire il tempo
  in silenzio presso l’utente principale. Scelga lì l’utente; la scelta viene
  memorizzata.
- **Modalità utente singolo:** solo se è attiva, l’import registra le voci
  senza persona identificabile sull’utente predefinito. È l’utente indicato in
  **Registra i tempi per l’utente**, altrimenti il titolare
  dell’organizzazione o il primo utente.

## Nuovo import e modifiche

- Un nuovo import non crea mai due volte voci già importate.
- Nell’import API WorkDiary riconosce ogni voce dal suo numero Kimai. Se una
  voce nota è cambiata in Kimai (inizio, fine, durata, descrizione),
  WorkDiary riprende la modifica. Se il tempo è già fatturato o esportato qui,
  WorkDiary non cambia nulla; il caso compare nell’inbox per conoscenza.
- Se un import API con **Recuperare i tempi di tutti gli utenti** non trova
  più, nel periodo interrogato, una voce importata in precedenza, questa
  viene considerata eliminata in Kimai. WorkDiary elimina allora anche il
  tempo, a meno che non sia fatturato; in tal caso nasce un caso nell’inbox.
- Nella via CSV WorkDiary riconosce una voce da orario, cliente, progetto,
  attività, descrizione ed e-mail. Se è stata modificata in Kimai, un nuovo
  caricamento crea una voce aggiuntiva. Gli import CSV non provocano
  eliminazioni. Per una sincronizzazione continua è più adatta la via API.
- I tag di Kimai vengono aggiunti, mai rimossi.

## Riscrivere i tempi in Kimai

La sezione **Riscrivere i tempi in Kimai** compare non appena è registrato un
accesso API ed è attivo **Attiva la riscrittura**.

- Vengono riscritti i tempi registrati in WorkDiary con inizio e fine, non
  ancora esportati, il cui progetto è collegato a un progetto Kimai. Questo
  collegamento nasce dall’import API – per i progetti trovati automaticamente
  e per i gruppi API che registra nell’inbox. Un import CSV non lo fornisce.
- WorkDiary non riscrive mai i tempi importati da Kimai.
- Kimai richiede un’attività per ogni timesheet: l’**ID attività Kimai per le
  ricontabilizzazioni** – il numero dell’attività in Kimai – vale per tutti i
  tempi riscritti. Descrizione e contrassegno fatturabile vengono trasmessi.
- **Esporta in Kimai** avvia la riscrittura dopo una conferma,
  facoltativamente per un periodo. Il messaggio indica le voci registrate,
  saltate e non riuscite.
- Un tempo riscritto vale in WorkDiary come esportato: non compare più sotto
  **Tempi aperti** e qui non viene più fatturato. Con **Ritrasferimento
  immediato dei nuovi tempi** ciò avviene già alla registrazione, senza
  possibilità di correzione.

## Riscrivere le correzioni

Con **Riscrivere le correzioni** WorkDiary trasmette a Kimai le modifiche ai
tempi importati tramite API (descrizione, inizio, fine, durata, fatturabile)
e la loro eliminazione. Prima WorkDiary confronta lo stato attuale in Kimai:
se nel frattempo la voce è stata modificata lì, WorkDiary non sovrascrive
nulla e crea invece un conflitto nell’inbox. I tempi fatturati e quelli
importati via CSV non vengono mai riscritti. Il trasferimento avviene in
background e viene ripetuto in caso di errore.

## Errori frequenti

- **Nessun accesso API registrato** al posto della sezione di import: nelle
  impostazioni del plugin mancano l’URL di base o il token.
- «Nessun ID attività Kimai registrato — ricontabilizzazione non possibile.»:
  inserisca il numero di un’attività Kimai.
- «Nessun progetto è associato a un progetto Kimai»: esegua prima un import
  API oppure registri i gruppi API nell’inbox.
- Molti casi «Utente sconosciuto»: gli indirizzi e-mail in Kimai o la colonna
  e-mail non corrispondono agli indirizzi e-mail in WorkDiary. Associ ogni persona
  una volta nell’inbox.
- Arrivano solo i tempi dell’utente del token: in Kimai gli manca il permesso
  view_other_timesheet, oppure **Recuperare i tempi di tutti gli utenti** è
  disattivato.
- L’import CSV non crea nulla: la riga d’intestazione deve contenere almeno
  una data e un orario di fine o una durata.
- L’API non è raggiungibile: la pagina mostra l’errore e non viene importato
  nulla. Inserisca l’indirizzo senza /api, controlli il token e usi
  **Verifica connessione**. Se l’istanza si trova in una rete interna,
  WorkDiary segnala un indirizzo privato; attivi **Consenti indirizzi
  privati**. Se il gestore ha bloccato questa autorizzazione, l’istanza ha
  bisogno di un indirizzo raggiungibile pubblicamente. Se gli errori si
  accumulano, WorkDiary disattiva automaticamente il plugin; risolta la causa,
  lo reimposti nella pagina **Plugin** con **Reset e riattiva**.
