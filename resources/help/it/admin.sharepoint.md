---
title: "Archiviazione SharePoint"
topic: admin.sharepoint
version: 1
keywords:
    - SharePoint
    - SharePoint Online
    - raccolta documenti
    - replicare documenti
    - archiviare in SharePoint
    - Microsoft 365
    - scegliere un sito
    - replica
    - prova di consegna
    - archiviare fatture
    - conflitto di replica
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - documents.manage
    - admin.integration-inbox
    - cloud-intake.overview
---

La pagina **Archiviazione SharePoint** replica i documenti approvati di
WorkDiary in una raccolta documenti di SharePoint Online tramite Microsoft
Graph e, se lo desidera, anche i PDF delle fatture emesse e dei protocolli
firmati. WorkDiary resta il sistema di riferimento: da SharePoint non torna
indietro nulla, e le modifiche ai file replicati in SharePoint emergono come
conflitto, senza mai essere acquisite in silenzio. Per ogni trasferimento
WorkDiary registra una prova di consegna (checksum, data e ora, destinazione).

Prelevare file da SharePoint verso WorkDiary in sola lettura è un'altra
funzione: l'**Ingresso documenti cloud**.

## Prerequisiti

- Il plugin **SharePoint** è attivato in **Plugin**. Successivamente compare la
  voce **Archiviazione SharePoint** nel menu di sistema (icona a ingranaggio
  **Sistema**), nel gruppo **Plugin**.
- Esiste una registrazione app in Microsoft Entra ID con ID client e segreto
  client. O il gestore ha inserito un'app per l'intera installazione, oppure la
  Sua organizzazione usa la propria app dalle impostazioni del plugin
  **Microsoft 365** (**ID client (registrazione app propria)**, **Segreto
  client**, **Tenant (ID directory)**). Un'app propria deve conoscere il Suo
  indirizzo WorkDiary con il percorso /admin/sharepoint/oauth/callback come
  URI di reindirizzamento di tipo «Web». Se l'app manca, la pagina mostra un
  avviso al posto del pulsante di connessione.
- Le serve un account Microsoft 365 con diritto di scrittura sulla raccolta di
  destinazione. La connessione opera con i diritti di questo account. Se il
  gestore ha limitato l'accesso ai siti autorizzati singolarmente
  (Sites.Selected), un amministratore del tenant deve inoltre autorizzare il
  sito desiderato.
- La pagina è riservata agli amministratori della Sua organizzazione. Ogni
  organizzazione ha una connessione SharePoint.

## Connettersi

1. Clicchi su **Connetti con Microsoft 365**. Si apre l'accesso a Microsoft;
   effettui l'accesso e acconsenta alle autorizzazioni.
2. Microsoft La riporta alla pagina. Il messaggio «Connesso con Microsoft 365.
   Ora scelga sito + raccolta.» conferma la connessione.

La procedura deve essere conclusa dalla stessa persona che l'ha avviata, nella
stessa sessione. Altrimenti compare «Il flusso OAuth è scaduto o non è
valido»; in tal caso riavvii la connessione.

## Scegliere la destinazione: sito e raccolta documenti

1. Nella sezione **Destinazione: sito + raccolta documenti** inserisca nel
   campo **Cerca sito** il nome o una parola chiave del sito e clicchi su
   **Cerca**.
2. Clicchi sul sito nell'elenco dei risultati. Viene contrassegnato come
   **Selezionato** e WorkDiary carica le sue raccolte documenti.
3. In **Raccolta documenti** scelga la raccolta e clicchi su **Salva**.
   **Destinazione attuale** mostra poi sito e raccolta.

Al salvataggio WorkDiary verifica sito e raccolta presso Microsoft; una
raccolta che non appartiene al sito scelto viene rifiutata.

## Regole delle cartelle e contenuti replicati

Nella sezione **Regole cartelle + origini** stabilisce cosa viene replicato e
dove:

- **Cartella predefinita** (precompilata con «Dokumente»): sottocartella della
  raccolta per tutti i documenti senza una regola propria.
- **Attivo**: attiva o disattiva la replica.
- **Contenuti replicati**: **Documenti (DMS)**, **Fatture (PDF)** e
  **Protocolli (PDF)**. Senza selezione vengono replicati solo i documenti.
- **Tipo di documento → cartella**: per ogni riga scelga un tipo di documento e
  inserisca una sottocartella relativa alla raccolta. Le righe vuote vengono
  ignorate; dopo ogni salvataggio sono disponibili altre tre righe vuote. I
  tipi compaiono nell'elenco con il loro nome breve inglese, ad esempio
  contract per i contratti o invoice per le fatture.

Clicchi poi su **Salva**.

Ecco come WorkDiary archivia i file:

- **Documenti** nella cartella del loro tipo o nella cartella predefinita. Il
  nome del file è composto da «document-», un numero interno e l'estensione;
  così una nuova versione sostituisce lo stesso file.
- **Fatture** nella cartella invoices, con una sottocartella per anno; il nome
  del file è il numero della fattura.
- **Protocolli** nella cartella protocols, con una sottocartella per anno.

Fatture e protocolli non seguono le regole delle cartelle.

## Quando avviene la replica

- **Automaticamente in caso di eventi:** quando un documento ottiene lo stato
  **Attivo** (approvato) o una nuova versione, WorkDiary trasferisce tale
  versione. Le semplici modifiche ai metadati non avviano un nuovo
  trasferimento. Quando una fattura viene emessa o un protocollo firmato,
  segue il relativo PDF, purché il contenuto corrispondente sia selezionato.
- **In background con ripetizione:** il trasferimento passa da una coda. Se non
  riesce, viene ripetuto automaticamente; nessun file viene scritto due volte.
- **Replica ora:** mette in coda tutti i documenti attivi dell'organizzazione,
  ad esempio dopo la prima configurazione. WorkDiary salta i file invariati.
  Questo pulsante non comprende fatture e protocolli; questi vengono
  trasferiti al momento dell'emissione o della firma.

Non esiste una pianificazione fissa. WorkDiary legge da SharePoint solo per
verificare se un file replicato vi è stato modificato.

## Risolvere i conflitti

Se un file replicato è stato modificato in SharePoint, WorkDiary non lo
sovrascrive. Compare invece una voce nell'**Inbox di riconciliazione** con
l'avviso «Modifica esterna rilevata — replica sospesa (nessuna
sovrascrittura).» Per i documenti della gestione documentale sono disponibili
tre azioni:

- **Sovrascrivi remoto**: la versione di WorkDiary sostituisce il file in
  SharePoint; la modifica apportata lì va persa.
- **Importa come nuova versione**: la versione di SharePoint viene acquisita
  come nuova versione del documento.
- **Scollega la replica**: questo singolo documento non viene più replicato;
  la connessione resta attiva.

L'**Inbox di riconciliazione** è accessibile alle persone autorizzate a
gestire la fatturazione.

## Disconnettere e riconnettere

**Disconnetti** rimuove le chiavi di accesso della connessione. I file già
replicati restano in SharePoint. Destinazione e regole delle cartelle restano
salvate; dopo un nuovo **Connetti con Microsoft 365** si prosegue con le stesse
impostazioni.

## Problemi tipici

- **Nessun pulsante di connessione:** la pagina segnala la mancanza della
  registrazione app. Inserisca ID client e segreto client (vedi prerequisiti)
  oppure si rivolga al gestore.
- **Accesso interrotto:** «Microsoft non ha restituito un codice di
  autorizzazione»: l'accesso è stato annullato o il consenso negato. Se il Suo
  tenant richiede il consenso di un amministratore, un amministratore Entra
  deve concedere l'autorizzazione all'app.
- **Nessun sito trovato:** verifichi il termine di ricerca. Con accesso
  limitato, l'amministratore del tenant deve autorizzare il sito.
- **Sito o raccolta rifiutati:** «Il sito scelto non è raggiungibile o non è
  autorizzato.» oppure «Nessuna raccolta documenti trovata in questo sito.»:
  l'account collegato non ha accesso, oppure il sito non ha una raccolta.
- **Stato Inattivo, Replica ora assente:** la connessione è disconnessa,
  **Attivo** è disattivato, non è stata scelta una raccolta, oppure la
  connessione è stata sospesa dopo ripetuti errori consecutivi. Una volta
  eliminata la causa, **Disconnetti** e una nuova connessione azzerano il
  conteggio degli errori.
- **Verificare lo stato:** accanto al titolo della pagina compare l'ultimo
  stato verificato; **Verifica connessione** lo controlla subito.
