---
title: "Import Clockify"
topic: admin.clockify
version: 1
keywords:
    - Clockify
    - importare tempi
    - report dettagliato
    - Clockify CSV
    - API Clockify
    - passare da Clockify
    - trasferire i tempi
    - assistenza remota in Clockify
    - associazione utenti
    - riscrivere le correzioni
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.kimai
    - admin.toggl
    - admin.scheduler
    - admin.organization-settings
---

La pagina **Import Clockify** porta in WorkDiary le registrazioni di tempo di
Clockify – come report dettagliato (CSV) caricato oppure direttamente tramite
l’API di Clockify. Se lo desidera, trasferisce inoltre a Clockify i tempi
registrati in WorkDiary, per esempio sessioni di assistenza remota, e rimanda
le correzioni ai tempi importati. Trova la pagina nel menu di sistema
(l’ingranaggio **Sistema** nella barra in alto) sotto **Plugin** → **Import
Clockify**, non appena il plugin è attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce Clockify. Attivazione e impostazioni
  valgono solo per l’organizzazione corrente.
- La pagina e le impostazioni sono riservate agli amministratori.
  L’**Inbox di riconciliazione**, in cui risolve i casi aperti, è accessibile
  anche alla contabilità.
- Per la via CSV basta un report dettagliato di Clockify.
- Per la via API serve una chiave API (in Clockify sotto Profile → Advanced
  → API). Il piano gratuito di Clockify consente solo 30 richieste API
  all’ora; lì la via CSV è quella consigliata.

## Configurazione

Le credenziali si inseriscono nella pagina **Plugin** tramite **Configura**
sulla voce Clockify:

1. **API key Clockify**: la chiave di Clockify. Viene memorizzata cifrata; un
   campo vuoto mantiene il valore precedente al salvataggio.
2. **ID spazio di lavoro**: facoltativo. Se vuoto, WorkDiary usa lo spazio di
   lavoro predefinito della chiave API.
3. **URL base API** e **URL di base dell'API Reports**: da modificare solo se
   il Suo account si trova su un’istanza regionale di Clockify; il testo di
   aiuto nella finestra di dialogo riporta un esempio.
4. **Finestra di sincronizzazione (giorni)**: quanto indietro guarda un
   import API senza periodo e fin dove arriva il trasferimento orario
   (predefinito 30 giorni).
5. **Acquisisci stato fatturabile**: attivo riprende il contrassegno
   fatturabile da Clockify; disattivato non contrassegna mai come fatturabili
   i tempi importati.
6. **Modalità utente singolo** e **Registra i tempi per l’ID utente**: solo
   per postazioni singole, vedi sotto.
7. Facoltativamente **Attiva il trasferimento dei tempi** e **Riscrivere le
   correzioni**.
8. **Salva**. Con **Verifica connessione** nella finestra di dialogo controlla
   l’accesso. Senza chiave API il plugin segnala la modalità CSV – non è un
   errore.

## Importare i tempi

**Carica CSV:** in Clockify esporti il report dettagliato in formato CSV
(Clockify → Reports → Detailed → Export → CSV), selezioni il file nella
pagina **Import Clockify** e clicchi su **Importa**. WorkDiary riconosce le
colonne dalla riga d’intestazione – Project, Client, Description, Task, Email,
Tags, Billable, Start Date, Start Time, End Date, End Time e Duration (h)
oppure Duration (decimal). Le colonne non necessarie possono mancare; sono
obbligatori Start Date e un orario di fine oppure una durata. Vanno bene sia
la virgola sia il punto e virgola, e il file può essere al massimo di 20 MB.

**Importa direttamente dall'API Clockify:** scelga facoltativamente un
periodo (**Da**, **Fino a**) e clicchi su **Importa da API**. WorkDiary
recupera le registrazioni di tutti gli utenti dello spazio di lavoro; senza
periodo, gli ultimi giorni secondo la finestra di sincronizzazione. L’import
salta le registrazioni in corso, senza fine.

Non esiste un import Clockify pianificato: ogni import parte da questa
pagina. Poi la pagina indica quante voci sono state create, saltate o lasciate
aperte nell’inbox, e quante non è stato possibile associare a un utente.

## Associare clienti, progetti e persone

- **Progetti:** l’import non crea clienti né progetti. Una voce viene
  registrata se il suo progetto viene trovato: tramite un’associazione
  memorizzata, altrimenti tramite lo stesso nome di progetto presso il
  cliente corrispondente. Se nelle impostazioni dell’organizzazione è attivo
  **Assegna i tempi ai progetti tramite parole chiave**, come ultimo passo
  aiuta una corrispondenza univoca per parola chiave.
- **Inbox di riconciliazione:** tutto il resto si raccoglie lì, raggruppato
  per cliente, progetto e task di Clockify. La scheda **Inbox di
  riconciliazione** della pagina mostra il numero di gruppi aperti, **Alla
  casella** vi porta. Lì sceglie il cliente, facoltativamente il cliente
  finale, e il progetto, poi registra il gruppo. L’associazione viene
  memorizzata; gli import successivi registrano senza chiedere.
- **Persone:** ogni tempo appartiene alla persona che lo ha registrato in
  Clockify. WorkDiary confronta il suo indirizzo e-mail (colonna Email o dato
  dell’API) con l’indirizzo e-mail degli utenti attivi. Senza corrispondenza
  nasce nell’inbox un caso «Utente sconosciuto» o «Voce senza segnale
  utente», invece di far finire il tempo in silenzio presso l’utente
  principale. Scelga lì l’utente; la scelta viene memorizzata.
- **Modalità utente singolo:** solo se è attiva, l’import registra le voci
  senza persona identificabile sull’utente predefinito. È l’utente indicato in
  **Registra i tempi per l’ID utente**, altrimenti il titolare
  dell’organizzazione o il primo utente.

## Nuovo import e modifiche

- Un nuovo import non crea mai due volte voci già importate.
- Nell’import API WorkDiary riconosce ogni voce dal suo identificativo
  Clockify. Se una voce nota è cambiata in Clockify (inizio, fine, durata,
  descrizione), WorkDiary riprende la modifica. Se il tempo è già fatturato o
  esportato qui, WorkDiary non cambia nulla; il caso compare nell’inbox per
  conoscenza.
- Se un import API non trova più, nel periodo interrogato, una voce importata
  o trasferita in precedenza, questa viene considerata eliminata in Clockify.
  WorkDiary elimina allora anche il tempo, a meno che non sia fatturato; in
  tal caso nasce un caso nell’inbox.
- Nella via CSV WorkDiary riconosce una voce da orario, cliente, progetto,
  task, descrizione ed e-mail. Se è stata modificata in Clockify, un nuovo
  caricamento crea una voce aggiuntiva. Gli import CSV non provocano
  eliminazioni.
- I tag di Clockify vengono aggiunti, mai rimossi.

## Trasferire i tempi a Clockify

Con **Attiva il trasferimento dei tempi** WorkDiary copia in Clockify i tempi
di lavoro registrati in WorkDiary con inizio e fine:

- Vengono trasferiti solo i tempi dei progetti collegati a un progetto
  Clockify. Un progetto vale come collegato non appena ha registrato su di
  esso un gruppo Clockify nell’Inbox di riconciliazione e in Clockify esiste
  un progetto con lo stesso cliente e lo stesso nome. I progetti che l’import
  ha trovato solo tramite il nome non contano.
- Le registrazioni vengono sempre create per il titolare della chiave API –
  Clockify non consente altro.
- I nuovi tempi partono subito dopo la registrazione. Inoltre un’esecuzione
  oraria recupera ciò che ancora manca nella finestra di sincronizzazione.
  Con **Trasferisci a Clockify** nella sezione **Trasferire i tempi a
  Clockify** avvia il trasferimento a mano, facoltativamente per un periodo.
- A differenza di una riscrittura, il tempo resta fatturabile in WorkDiary.
  In seguito si comporta come un tempo importato: modifiche ed eliminazioni
  vengono sincronizzate in entrambe le direzioni.
- I tempi già trasferiti e quelli importati da Clockify vengono saltati.

## Riscrivere le correzioni

Con **Riscrivere le correzioni** WorkDiary trasmette a Clockify le modifiche
ai tempi importati o trasferiti tramite API (descrizione, inizio, fine,
durata, fatturabile) e la loro eliminazione. Prima WorkDiary confronta lo
stato attuale in Clockify: se nel frattempo la voce è stata modificata lì,
WorkDiary non sovrascrive nulla e crea invece un conflitto nell’inbox. I tempi
fatturati e quelli importati via CSV non vengono mai riscritti.

## Errori frequenti

- **Nessuna chiave API registrata** al posto della sezione di import: nelle
  impostazioni del plugin manca la chiave API.
- Il messaggio cita il piano Free con 30 richieste all’ora: la quota è
  esaurita. Importi via CSV oppure attenda; l’esecuzione successiva prosegue
  un trasferimento interrotto.
- «Clockify: nessun workspace determinabile»: inserisca l’**ID spazio di
  lavoro**.
- «Nessun progetto è associato a un progetto Clockify»: registri prima nella
  inbox dei gruppi Clockify sui progetti desiderati.
- Molti casi «Utente sconosciuto»: gli indirizzi e-mail in Clockify sono
  diversi da quelli in WorkDiary. Associ ogni persona una volta nell’inbox.
- Date errate nell’import CSV: le date in formato con barra in cui giorno e
  mese sono entrambi al massimo 12 vengono lette come mese/giorno. Imposti in
  Clockify un formato di data univoco.
- Se gli errori si accumulano, WorkDiary disattiva automaticamente il plugin;
  risolta la causa, lo reimposti nella pagina **Plugin** con **Reset e
  riattiva**.
