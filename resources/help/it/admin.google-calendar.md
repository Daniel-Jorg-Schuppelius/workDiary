---
title: "Collegare Google Calendar"
topic: admin.google-calendar
version: 2
keywords:
    - Google Calendar
    - calendario Google
    - appuntamenti verso Google
    - sincronizzare il calendario
    - Google Workspace
    - sincronizzazione calendario
    - calendario bidirezionale
    - esportare appuntamenti
    - pubblicare il calendario
    - Google Cloud Console
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - admin.import
    - admin.scheduler
---

La pagina **Google Calendar** trasferisce gli eventi di WorkDiary in un
calendario di un account Google. WorkDiary resta il sistema di riferimento: le
modifiche vengono riportate, gli eventi annullati ed eliminati scompaiono dal calendario
Google e le esecuzioni ripetute non creano duplicati. Se lo desidera, WorkDiary
rilegge inoltre il calendario e Le sottopone le modifiche esterne come proposte
da verificare.

## Prerequisiti

- Il plugin **Google Calendar** è attivato in **Plugin**. Successivamente
  compare la voce **Google Calendar** nel menu di sistema (icona a ingranaggio
  **Sistema**), nel gruppo **Plugin**.
- Esiste un client OAuth nella Google Cloud Console. O il gestore ne ha
  inserito uno per l'intera installazione, oppure la Sua organizzazione ne usa
  uno proprio: in **Plugin** apra la finestra **Configura** di **Google
  Calendar** e inserisca **ID client (app Google Cloud propria)** e **Client
  secret**. Un client proprio deve conoscere il Suo indirizzo WorkDiary con il
  percorso /admin/google-calendar/oauth/callback come URI di reindirizzamento
  autorizzato.
- Google classifica l'accesso al calendario come sensibile. L'app ha quindi
  bisogno di una verifica da parte di Google, oppure imposta la schermata di
  consenso su tipo «Interno» in Google Workspace.
- Se manca un client OAuth, la pagina mostra un avviso al posto del pulsante di
  connessione.
- Le serve un account Google con diritto di scrittura sul calendario di
  destinazione. La connessione può modificare appuntamenti e leggere l'elenco
  dei calendari.
- La pagina è riservata agli amministratori della Sua organizzazione. Ogni
  organizzazione ha una connessione.

## Connettersi

1. Clicchi su **Collega a Google**. Si apre l'accesso a Google; effettui
   l'accesso e consenta l'accesso ai dati.
2. Google La riporta alla pagina. Il messaggio «Account Google collegato.»
   conferma la connessione; accanto al titolo compare l'indicatore
   **Connesso**.

La procedura deve essere conclusa dalla stessa persona che l'ha avviata, nella
stessa sessione.

## Scegliere il calendario di destinazione

Nella sezione **Calendario di destinazione** scelga in **Calendario** uno dei
calendari dell'account collegato. Senza scelta vale il **Calendario
principale**. Lì attiva all'occorrenza anche **Bidirezionale: importa le
modifiche esterne come proposte**. Clicchi poi su **Salva**. Se cambia
calendario, l'importazione riparte da zero.

## Cosa viene trasferito e quando

- **Contenuto:** gli eventi da 30 giorni indietro a 180 giorni avanti, con
  titolo, descrizione, orario e luogo (sale prenotate). Gli eventi annullati
  vengono rimossi dal calendario Google.
- **Momento:** una sincronizzazione viene eseguita ogni giorno, per
  impostazione predefinita alle 4:55; modifica la frequenza in **Attività
  pianificate**. **Pubblica ora** la avvia subito in background.
- **Notifiche:** le notifiche con una scadenza partono subito come voce di
  calendario se una regola di notifica usa il canale **Calendario**.
- **Senza modalità bidirezionale** WorkDiary non legge alcun appuntamento dal
  calendario Google.

## Importazione bidirezionale

Con la modalità bidirezionale attiva, WorkDiary rilegge il calendario di
destinazione ogni ora. Ne derivano esclusivamente voci nell'**Inbox di
riconciliazione**, mai appuntamenti creati in automatico:

- Un nuovo appuntamento che non proviene da WorkDiary diventa una proposta.
- Un evento trasferito e poi modificato in Google diventa un conflitto,
  altrimenti la sincronizzazione successiva sovrascriverebbe la modifica in
  silenzio.
- Un evento trasferito e poi eliminato o annullato in Google compare come
  «Appuntamento eliminato in Google Calendar».
- Le serie compaiono come singoli appuntamenti nel periodo di importazione e si
  possono acquisire o scartare come gruppo.

L'**Inbox di riconciliazione** è accessibile alle persone autorizzate a gestire
la fatturazione. Indipendentemente dalla modalità bidirezionale,
l'importazione di timbrature e tempi di progetto offre il calendario collegato
come fonte.

## Disconnettere e riconnettere

**Disconnetti** rimuove l'accesso. Gli appuntamenti già trasferiti restano nel
calendario Google. Con **Collega a Google** ripristina la connessione in
qualsiasi momento; il calendario scelto resta salvato e il conteggio degli
errori riparte da zero. Finché la connessione è disturbata, un'attività
operativa lo segnala.

## Problemi tipici

- **Nessun pulsante di connessione:** non è inserito alcun client OAuth (vedi
  prerequisiti).
- **«Il flusso OAuth è scaduto o non è valido. Riprovare.»** L'accesso è durato
  troppo o è stato concluso in un'altra sessione. Riavvii la connessione.
- **«La connessione è stata rifiutata o annullata.»** Il consenso è stato
  negato, oppure Google non ammette l'app per questo account, ad esempio perché
  non è ancora verificata. Controlli la schermata di consenso nella Google
  Cloud Console.
- **Indicatore Non raggiungibile:** l'API di Google Calendar non è
  raggiungibile o nega l'accesso, ad esempio dopo una revoca nell'account
  Google. **Disconnetti** e ripeta il collegamento.
- **«Il calendario selezionato non è stato trovato.»** Il calendario è stato
  eliminato oppure l'account ha perso l'accesso. Ne scelga un altro.
- **Sospensione:** dopo ripetuti errori consecutivi WorkDiary sospende la
  connessione; ricompare allora **Collega a Google**. Verifichi la causa e
  ripeta il collegamento.
