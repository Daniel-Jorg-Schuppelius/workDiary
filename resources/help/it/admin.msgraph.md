---
title: "Collegare Microsoft 365"
topic: admin.msgraph
version: 1
keywords:
    - Microsoft 365
    - Office 365
    - calendario Outlook
    - inviare e-mail tramite Microsoft
    - contatti Outlook
    - Microsoft To Do
    - OneNote
    - riunione Teams
    - consenso amministratore
    - Entra ID
    - risposta automatica
    - Exchange Online
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.sharepoint
    - admin.google-calendar
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - knowledge.collections
    - cloud-intake.overview
    - backup-targets.overview
---

La pagina **Microsoft 365** riunisce le connessioni a Microsoft 365 tramite
Microsoft Graph: calendario, invio e-mail, contatti verso Outlook, Microsoft To
Do e l'importazione da OneNote, oltre all'autorizzazione a livello di tenant.
Ogni funzione ha una propria connessione, con un proprio accesso e soltanto le
autorizzazioni di cui ha bisogno: collega solo ciò che usa davvero. Ogni
connessione vale per l'intera organizzazione e opera con l'account Microsoft
che concede il consenso durante l'accesso.

## Prerequisiti

- Il plugin **Microsoft 365** è attivato in **Plugin**. Successivamente
  compare la voce **Microsoft 365** nel menu di sistema (icona a ingranaggio
  **Sistema**), nel gruppo **Plugin**.
- Esiste una registrazione app in Microsoft Entra ID. O il gestore ha inserito
  un'app per l'intera installazione, oppure la Sua organizzazione ne usa una
  propria: in **Plugin** apra la finestra **Configura** di **Microsoft 365** e
  inserisca **ID client (registrazione app propria)**, **Segreto client** e
  **Tenant (ID directory)**. Il tenant è il GUID della Sua directory oppure uno
  dei valori common, organizations o consumers; se vuoto, vale il valore
  dell'app dell'installazione.
- Se manca un'app, la pagina mostra un avviso e i pulsanti di connessione
  mancano.
- Le serve un account Microsoft autorizzato a concedere il consenso. La pagina
  è riservata agli amministratori della Sua organizzazione.

## Calendario

In alto nella pagina collega il calendario con **Collega a Microsoft 365**.
Dopodiché vi trova **Pubblica ora** e **Disconnetti**; accanto al titolo un
indicatore mostra **Connesso**, **Non raggiungibile** o **Inattivo**.

- **Direzione:** gli eventi di WorkDiary vengono trasferiti nel calendario
  dell'account collegato, da 30 giorni indietro a 180 giorni avanti, con
  titolo, descrizione, orario e luogo (sale prenotate). Le modifiche vengono
  riportate, gli eventi annullati vi vengono rimossi e le esecuzioni ripetute
  non creano duplicati. WorkDiary resta il sistema di riferimento.
- **Momento:** una sincronizzazione viene eseguita ogni giorno, per
  impostazione predefinita alle 4:45; modifica la frequenza in **Attività
  pianificate**. **Pubblica ora** la avvia subito in background. Inoltre le
  notifiche con una scadenza partono subito come voce di calendario se una
  regola di notifica usa il canale **Calendario**.
- **Calendario di destinazione:** con una connessione attiva scelga in
  **Calendario**, nella sezione omonima, un calendario dell'account; senza
  scelta vale il **Calendario predefinito**. Clicchi poi su **Salva**.
- **Creare i nuovi eventi come riunioni Teams (link di partecipazione):** gli
  eventi trasferiti da quel momento ricevono un link di partecipazione Teams.
  L'opzione non modifica gli eventi già trasferiti.
- **Bidirezionale: importare le modifiche esterne come proposte:** il
  calendario di destinazione viene riletto ogni ora e Microsoft segnala
  inoltre subito le modifiche. I nuovi appuntamenti esterni diventano
  proposte, le modifiche agli eventi trasferiti diventano conflitti e gli
  appuntamenti eliminati compaiono come «Appuntamento eliminato in Microsoft
  365», tutto nell'**Inbox di riconciliazione**, mai come appuntamento creato
  alla cieca. Le serie compaiono come singoli appuntamenti e lì si possono
  acquisire o scartare come gruppo. Se cambia il calendario di destinazione,
  l'importazione riparte da zero.

La connessione al calendario serve inoltre a:

- **Verifica disponibilità (Microsoft 365)** nella finestra di un evento:
  mostra libero o occupato per i partecipanti scelti, senza dettagli degli
  appuntamenti.
- l'importazione di timbrature e tempi di progetto, che offre il calendario
  collegato come fonte.
- il riquadro **Team (stato Teams)** nella pagina **Orologio marcatempo**.
  Compare solo se l'installazione ha abilitato l'accesso in lettura allo stato
  Teams e la connessione al calendario è stata ristabilita in seguito.

## Invio e-mail tramite Microsoft 365

Con **Connetti l’invio e-mail** un account consente a WorkDiary di inviare
e-mail a suo nome, ad esempio fatture, solleciti e notifiche, senza accesso
SMTP. Dopodiché vede l'**Account connesso** e imposta:

- **Indirizzo mittente (opzionale)**: se vuoto, l'account invia a proprio
  nome. Un indirizzo diverso, ad esempio una casella condivisa, richiede in
  Exchange il diritto «Invia come» (Send As) e un'autorizzazione aggiuntiva
  che il gestore abilita per l'app.
- **Salvare una copia nella cartella Posta inviata**.
- **Salva**.

**Invia e-mail di prova** invia subito un messaggio tramite questa
connessione, al **Destinatario (facoltativo)** oppure, se vuoto, all'account
connesso. La prova usa lo stesso indirizzo mittente dell'invio reale, così i
diritti di invio mancanti emergono subito. Se WorkDiary invii davvero le sue
e-mail tramite questa connessione lo decide il gestore dell'installazione; se
non è impostato, la scheda mostra un avviso. **Disconnetti l’invio e-mail**
rimuove l'accesso.

## Inviare i contatti a Outlook

Dopo **Connetti l’invio dei contatti** compare il pulsante **Verso Outlook**
nella pagina di dettaglio di un cliente. Trasferisce il cliente come contatto
in Outlook dell'account collegato: nome, referente, azienda, e-mail, telefono,
numero di cellulare, sito web e indirizzo. Un nuovo trasferimento aggiorna il
contatto invece di duplicarlo; se è stato eliminato in Outlook, WorkDiary lo
ricrea. Il trasferimento avviene solo premendo il pulsante e solo in questa
direzione; serve il diritto di modificare il cliente.

I contatti Outlook di questo account servono inoltre come rubrica per il
confronto di numeri di telefono sconosciuti, ad esempio nell'importazione
FRITZ!Box.

## Sincronizzare Microsoft To Do

1. Clicchi su **Connetti la sincronizzazione To Do**.
2. Crei un collegamento: scelga la **Lista To Do**, come **Destinazione** un
   **Progetto** o il **Kanban globale**, selezioni il **Progetto** se la
   destinazione è un progetto, imposti la **Direzione** (**Entrambe le
   direzioni**, **Solo To Do → WorkDiary** o **Solo WorkDiary → To Do**) e
   clicchi su **Collega**.
3. La tabella mostra tutti i collegamenti. **Rimuovi** ne elimina uno; le
   attività già sincronizzate vengono mantenute.

Ogni lista To Do può essere collegata una sola volta; un nuovo collegamento
della stessa lista sostituisce quello precedente. Vengono sincronizzati
titolo, descrizione, stato (aperto, in corso, completato), priorità e data di
scadenza. La sincronizzazione viene eseguita ogni ora; le modifiche in
WorkDiary partono inoltre subito, e Microsoft segnala subito le modifiche alle
liste che importano. Se entrambe le parti hanno modificato la stessa attività,
nasce un conflitto nell'**Inbox di riconciliazione**: l'ultima modifica non
vince semplicemente. WorkDiary non trasferisce mai le eliminazioni; le attività
eliminate in To Do vengono solo contrassegnate. Questa sincronizzazione non
conosce sottoattività, assegnatari né sezioni.

## Importare da OneNote

1. In **Plugin** attivi l'opzione **Consenti importazione OneNote** nella
   finestra **Configura** di **Microsoft 365**. Fino ad allora la scheda mostra
   **Disattivato** e la connessione non richiede l'accesso ai blocchi
   appunti.
2. Clicchi su **Connetti OneNote**. L'accesso è in sola lettura.
3. **Vai a «Conoscenza»** porta all'importazione: lì **Importa OneNote**
   acquisisce un blocco appunti una volta o su richiesta come note o articoli
   di conoscenza. Il blocco appunti diventa una raccolta, le sue sezioni
   diventano sottoraccolte. Non c'è riscrittura né sincronizzazione continua.

## App Entra e autorizzazione a livello di tenant

Se un criterio del Suo tenant Microsoft impedisce agli utenti di concedere il
consenso da soli, un amministratore Entra concede le autorizzazioni una volta
per l'intera organizzazione: **Concedi per l'organizzazione (admin
consent)**. L'accesso richiede un ruolo di amministratore Entra nel tenant di
destinazione. L'autorizzazione comprende calendario, invio e-mail, contatti,
attività e ingresso documenti e, con l'importazione OneNote attivata, anche la
lettura dei blocchi appunti. In seguito gli utenti si collegano senza una
richiesta di consenso propria.

**URI di reindirizzamento per una registrazione app propria** elenca gli
indirizzi che un'app propria deve registrare come URI di reindirizzamento di
tipo «Web»: per calendario, invio e-mail, contatti, attività, ingresso
documenti, admin consent e, solo per l'app dell'installazione, la destinazione
di backup. L'indirizzo per OneNote manca in questo elenco: con un'app propria
inserisca anche il Suo indirizzo WorkDiary con il percorso
/admin/msgraph/onenote/oauth/callback. Se l'**Archiviazione SharePoint** usa la
stessa app, ne fa parte anche il percorso /admin/sharepoint/oauth/callback.

## Altre funzioni del plugin

- **Impostare la risposta automatica di Outlook per le ferie approvate** (nelle
  impostazioni del plugin, disattivata per impostazione predefinita): non
  appena una richiesta di ferie è approvata in via definitiva, WorkDiary imposta
  la risposta automatica nella casella della persona. A tale scopo l'app ha
  bisogno dell'autorizzazione applicativa MailboxSettings.ReadWrite con admin
  consent. Gli errori non bloccano l'approvazione.
- L'**Ingresso documenti cloud** e le **Destinazioni di backup cloud** usano
  connessioni Microsoft proprie, che configura in quelle pagine.

## Disconnettere e riconnettere

Ogni scheda ha la propria disconnessione. Essa rimuove le chiavi di accesso di
quella connessione; gli appuntamenti trasferiti e i contatti Outlook restano
presso Microsoft. Può riconnettersi in qualsiasi momento; WorkDiary azzera
allora anche il conteggio degli errori. Se una connessione è stata sospesa dopo
ripetuti errori consecutivi, il pulsante di connessione ricompare.

## Problemi tipici

- **Nessun pulsante di connessione:** manca la registrazione app (vedi
  prerequisiti).
- **«Il flusso OAuth è scaduto o non è valido. Riprovare.»** L'accesso è durato
  troppo o è stato concluso in un'altra sessione. La persona che effettua il
  collegamento deve concludere la procedura personalmente.
- **«La connessione è stata rifiutata o annullata.»** Il consenso è stato
  negato. Se l'account non può concederlo da solo, usi l'admin consent.
- **Indicatore Non raggiungibile:** Microsoft Graph non è raggiungibile o nega
  l'accesso. Verifichi l'account e ripeta il collegamento.
- **«Invio di prova non riuscito: …»** Con un indirizzo mittente diverso manca
  spesso il diritto «Invia come».
- **«La lista To Do selezionata non è più disponibile.»** La lista è stata
  eliminata in To Do o non appartiene all'account collegato.
- **Avviso sulle connessioni secondarie:** se il controllo di stato in
  **Plugin** segnala che le connessioni secondarie Microsoft 365 richiedono
  attenzione, una connessione per ingresso documenti, backup o invio e-mail è
  disturbata. Ripeta l'accesso in quel punto.
