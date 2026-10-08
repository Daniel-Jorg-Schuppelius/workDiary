---
title: "Connessione Todoist"
topic: admin.todoist
version: 1
keywords:
    - Todoist
    - sincronizzare attività
    - sincronizzazione attività
    - collegare Todoist
    - associazione progetti
    - preflight
    - sezioni
    - associare assegnatari
    - kanban
    - conflitto attività
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - work.overview
    - projects.manage
    - admin.scheduler
---

La pagina **Todoist** sincronizza le attività tra WorkDiary e Todoist. Vengono
sincronizzati solo i progetti Todoist che associa esplicitamente a un
progetto WorkDiary o al kanban globale; i conflitti arrivano nella inbox di
integrazione e nulla viene sovrascritto o eliminato in silenzio. Trova la
pagina nel menu di sistema (l’ingranaggio **Sistema** nella barra in alto)
sotto **Plugin** → **Todoist**, non appena il plugin è attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce Todoist.
- La pagina è riservata agli amministratori.
- Serve un’app Todoist registrata. O il gestore della Sua installazione ne ha
  depositata una, oppure inserisce la propria: nella pagina **Plugin**
  tramite **Configura** sulla voce Todoist, nei campi **ID client (app
  Todoist propria)** e **Client secret**. Se vuoti, vale l’app
  dell’installazione. La Sua app deve registrare in Todoist, come URI di
  reindirizzamento, l’indirizzo della Sua installazione WorkDiary con il
  percorso `/admin/todoist/oauth/callback`.
- Per ogni organizzazione esiste esattamente una connessione Todoist, quindi
  un solo account Todoist.

## Collegarsi a Todoist

1. Apra la pagina **Todoist**. La sezione **Connessione** indica in anticipo
   quali dati vengono trasmessi: titoli, descrizioni, stati, scadenze e
   assegnatari delle attività associate. WorkDiary non richiede diritti di
   eliminazione.
2. Clicchi su **Connetti a Todoist** e acceda a Todoist. Dopo l’autorizzazione
   torna alla pagina.
3. La pagina mostra poi **Stato**, **Account**, **Connesso da** e **Ultima
   sincronizzazione**. **Rinnova connessione** La fa accedere di nuovo,
   **Disconnetti** termina la connessione; associazioni e collegamenti
   vengono conservati.

## Associare i progetti

Con una connessione attiva compare la tabella **Associazioni progetti**. Sotto
la tabella crea una nuova associazione:

1. **Progetto Todoist**: scelta dal Suo account Todoist.
2. **Destinazione**: **Progetto WorkDiary** (scelga poi il progetto; l’elenco
   mostra al massimo 500 progetti) oppure **Kanban globale** per attività
   senza progetto.
3. **Direzione**: **Todoist → WorkDiary**, **WorkDiary → Todoist** oppure
   **Bidirezionale**.
4. **Associa**. Ogni nuova associazione parte come **Bozza** e non
   sincronizza ancora nulla.

Nella tabella apre per ogni associazione il **Preflight**, la commuta con
**Attiva** o **Metti in pausa** e la rimuove con l’icona del cestino (i
riferimenti vengono conservati). La colonna **Ultima esecuzione** mostra
orario e contatori: create, aggiornate, invariate e conflitti.

## Preflight: assegnatari e sezioni

Prima dell’attivazione il **Preflight** mostra ciò che la sincronizzazione
troverà:

- **Indicatori**: attività attive, sottoattività, attività ricorrenti,
  scadenze con orario, assegnatari non associabili e attività già collegate.
- **Associazione assegnatari**: per ogni collaboratore Todoist sceglie un
  utente WorkDiary e clicca su **Salva**. Un indirizzo e-mail uguale compare
  solo come **Suggerimento**; l’assegnazione vale solo dopo la Sua scelta.
  Senza associazione un’attività resta senza assegnatario.
- **Sezioni → stato**: per ogni sezione Todoist sceglie **Aperta** o **In
  corso**. Le sezioni non associate lasciano invariato lo stato.

Solo dopo mette in funzione l’associazione con **Attiva**.

## Che cosa viene sincronizzato

- **Todoist → WorkDiary**: ogni attività Todoist attiva diventa un’attività
  WorkDiary nel progetto di destinazione o nel kanban globale. Vengono
  sincronizzati titolo, descrizione, priorità (Todoist da p1 a p4
  corrisponde a Urgente, Alta, Media, Bassa), scadenza, durata come budget di
  tempo, assegnatario e stato. Completata in Todoist significa qui
  **Completato**. Le sottoattività restano sotto la loro attività madre.
- **WorkDiary → Todoist**: le nuove attività create dopo l’attivazione nel
  progetto associato o nel kanban globale vengono create da WorkDiary in
  Todoist. Anche le modifiche alle attività collegate vengono trasmesse; un
  cambio di stato sposta l’attività nella sezione associata oppure la
  completa o la riapre. Le attività che esistevano già prima dell’attivazione
  non vengono trasferite.
- **Bidirezionale** unisce entrambe le direzioni.
- Per le attività collegate la finestra dell’attività mostra il link **Apri
  in Todoist**.

## Quando avviene la sincronizzazione

- Ogni ora WorkDiary recupera da Todoist le modifiche dall’ultima esecuzione.
  La frequenza si modifica sotto **Attività pianificate**.
- **Sincronizza ora** avvia una sincronizzazione completa in background. Solo
  questa nota anche le attività sparite dal progetto Todoist senza essere
  eliminate, per esempio perché spostate.
- Le modifiche da WorkDiary partono subito tramite una coda e vengono ripetute
  in caso di errore.
- Se usa una propria app Todoist, può inserirvi anche un webhook verso
  l’indirizzo della Sua installazione con il percorso
  `/api/webhooks/todoist`. Esso avvia una sincronizzazione mirata in caso di
  modifiche; l’esecuzione oraria resta la fonte affidabile.

## Conflitti ed eliminazioni

- WorkDiary confronta ogni campo con il suo stato all’ultima
  sincronizzazione. Se un campo è stato modificato in modo diverso da
  entrambe le parti, nasce un conflitto nell’inbox; lì decide quale stato
  vale. Fino ad allora WorkDiary non trasmette quel campo.
- WorkDiary non propaga le eliminazioni in nessuna direzione. Se un’attività
  scompare in Todoist o un’attività collegata viene eliminata qui, nasce un
  caso nell’inbox.
- Anche una sottoattività la cui attività madre manca qui finisce nell’inbox.
- Se un’attività è stata completata in WorkDiary, la sua riapertura in
  Todoist non la ripristina.

**Inbox di integrazione** nella pagina apre l’Inbox di riconciliazione
filtrata su Todoist.

## Errori frequenti

- «Todoist non è configurato»: non è registrata alcuna app Todoist – né dal
  gestore né nelle impostazioni del plugin.
- «Stato OAuth non valido o scaduto»: l’accesso è durato troppo o è avvenuto
  in un’altra sessione. Si colleghi di nuovo.
- «Scambio del token non riuscito»: ID client, client secret o URI di
  reindirizzamento della Sua app non sono corretti.
- L’elenco dei progetti Todoist è vuoto: la connessione non raggiunge
  Todoist. Controlli lo stato nella pagina **Plugin** e rinnovi la
  connessione.
- Le attività arrivano senza assegnatario: il collaboratore Todoist non è
  ancora associato a un utente nel preflight.
- Non viene sincronizzato nulla: l’associazione è ancora in **Bozza** o **In
  pausa**.
