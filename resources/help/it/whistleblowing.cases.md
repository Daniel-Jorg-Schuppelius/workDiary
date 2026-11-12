---
title: "Canale di segnalazione – gestione dei casi"
topic: whistleblowing.cases
version: 5
keywords:
    - whistleblowing
    - segnalante
    - tutela del segnalante
    - canale interno
    - gestire una segnalazione
    - conferma di ricezione
    - caso di compliance
    - conflitto di interessi
    - accesso di emergenza
    - messaggi al segnalante
audience: []
modules:
    - module.compliance
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Qui Lei gestisce le segnalazioni ricevute da segnalanti interni ed
esterni. L’elenco **Segnalazioni whistleblower** si trova nel menu
**Conformità** → **Ufficio segnalazioni**. L’autorizzazione dell’ufficio
segnalazioni (ruolo **Ufficio segnalazioni**) è volutamente **separata**
dall’amministrazione: anche gli amministratori non hanno accesso senza
una propria assegnazione al caso. Ogni singolo accesso richiede il
permesso corrispondente **e** l’assegnazione al caso specifico; non
esistono eccezioni per gli amministratori.

Per l’accesso è necessaria una propria autenticazione a due fattori;
senza di essa WorkDiary La reindirizza alla configurazione.

**Elenco dei casi**: la panoramica mostra solo dati di base (**Numero
del caso**, **Categoria**, **Stato**, **Priorità**, **Ricezione entro**,
**Risposta entro**) – volutamente **senza anteprima del contenuto**.
Categoria e priorità compaiono solo quando Lei è assegnato al caso
(«Visibile dopo l'assegnazione»). Il contenuto di ogni caso è cifrato con
una chiave propria.

**Dettaglio del caso**: il fascicolo mostra **Informazioni sul caso**,
**Contenuto della segnalazione**, **Responsabile** e **Comunicazione e
note**. A seconda delle autorizzazioni può

- **Conferma ricezione** (termine **Ricezione entro**: 7 giorni dal
  ricevimento),
- in **Cambia stato**, scegliere il successivo stato consentito e
  applicarlo con **Imposta stato** – ad esempio «Ricevuta» → «Ricezione
  confermata» → «Valutazione preliminare» → «In lavorazione» (nel mezzo
  «In attesa del segnalante» o «Trasmessa») → «Chiusa – …»; una chiusura
  richiede una **Motivazione**, archiviata come nota interna,
- in **Assegna incaricato**, aggiungere una persona tramite il suo **ID
  utente** con un **Ruolo** (**Assegna**),
- registrare una **Nota interna** (**Salva nota**; mai visibile alla
  persona segnalante),
- inviare un **Messaggio alla persona segnalante** (**Invia**); compare
  nella sua casella protetta.

Gli allegati caricati dalla persona segnalante vengono archiviati in
forma cifrata e controllati contro i malware prima del rilascio. La scheda
**Allegati** indica nome e dimensione di ogni file; gli allegati rilasciati
si scaricano con l'icona **Scarica**. Altrimenti la scheda mostra lo stato
del controllo: **Analisi in corso** finché manca il risultato, **Respinto** o
**Analisi non riuscita** – WorkDiary non consegna questi file. Senza un
antivirus configurato gli allegati restano in analisi.

**Riservatezza e conflitti**:

- **Dichiara conflitto di interessi** (motivazione facoltativa) La
  esclude dal caso: la Sua assegnazione termina subito e non può
  revocare il blocco da sé.
- **Contrassegna la persona interessata** esclude una persona dal caso
  tramite il suo **ID utente** (**Segna**, nota facoltativa); le
  assegnazioni esistenti terminano subito.
- **Accesso di emergenza** concede a una persona non assegnata un accesso
  a tempo, per impostazione predefinita quattro ore (ID utente e
  motivazione obbligatoria, **Rilascia**). Può concederlo solo un'altra
  persona.
- **Esporta caso** crea un pacchetto cifrato con AES-256 (motivazione e
  password di almeno dodici caratteri, **Esporta**); trasmetta la password
  separatamente dal pacchetto.
- Ogni passaggio sul caso viene registrato in modo non manipolabile nel
  registro del caso.

**Eliminazione**: quando un caso è nello stato «Verifica di
conservazione», compare la scheda **Eliminazione controllata**. **Elimina
caso** e la conferma **Elimina definitivamente** distruggono la chiave
del caso: contenuto della segnalazione, messaggi, allegati e
assegnazioni vanno persi irrimediabilmente; resta solo una prova di
cancellazione priva di contenuti. L’operazione è irreversibile. Se vi si
oppongono un procedimento o un obbligo di conservazione, imposti invece
lo stato «Blocco della cancellazione (legal hold)».

**Filtri dell'elenco**: la barra sopra l'elenco filtra per **stato**, **categoria**, **priorità**, persona assegnata, stato delle scadenze («Scadenza superata», «Scadenza entro 14 giorni», «Riscontro in sospeso») e periodo di ricezione. Categoria e priorità si applicano solo ai casi a Lei assegnati, altrimenti l'elenco dei risultati rivelerebbe entrambe.

**Scadenze e riscontro**: se all'invio di un messaggio seleziona **Questo è il riscontro ai sensi dell'art. 17 HinSchG**, WorkDiary registra il momento nel caso; una semplice richiesta di chiarimento non conta. Da quel momento cessa il promemoria per il termine di riscontro. Gli incaricati assegnati ricevono promemoria privi di contenuto: 48 ore prima della scadenza della conferma di ricezione, 14 giorni e 48 ore prima del termine di riscontro (una volta ciascuno) e poi ogni giorno finché una scadenza è superata. Portare un caso direttamente da «Ricevuto» a «Verifica» conferma la ricezione.

**Altre azioni nel fascicolo**:

- **Revoca assegnazione** (icona accanto all'incaricato): la persona perde subito l'accesso.
- **Aggiungere un documento dell'ufficio segnalazioni**: il file viene cifrato come un allegato della persona segnalante ed è disponibile solo dopo la scansione; la persona segnalante non lo vede.
- Lo stato «Blocco della cancellazione» richiede **base giuridica**, **responsabile** e **data di verifica**; in quella data WorkDiary avvisa gli incaricati.
- Una riapertura (da «Chiuso – …» a «In lavorazione») richiede una **motivazione**; il termine di conservazione riparte dalla chiusura successiva.
- **Registro eventi**: con il permesso di consultazione del registro vede chi ha aperto, modificato o esportato il caso e quando, senza contenuti della segnalazione. Ogni accesso al registro viene a sua volta registrato.
