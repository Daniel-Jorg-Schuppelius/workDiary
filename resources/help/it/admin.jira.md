---
title: "Importazione di attività da Jira"
topic: admin.jira
version: 1
keywords:
    - Jira
    - Jira Cloud
    - importare ticket
    - Atlassian
    - sito
    - associazione di progetti
    - anteprima
    - importazione di attività
    - caso da verificare
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

La pagina **Jira** importa i ticket di progetti Jira Cloud selezionati come
attività nei Suoi progetti WorkDiary. Così registra i tempi sul lavoro
pianificato in Jira e lo fattura come di consueto. L’importazione legge
soltanto: WorkDiary non scrive nulla in Jira. Trova la pagina nel menu di
sistema (l’ingranaggio **Sistema**) sotto **Plugin** → **Jira**, non appena il
plugin è attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce Jira.
- Il modulo Kanban è concesso in licenza; la pagina è aperta agli
  amministratori.
- Il gestore della Sua installazione ha configurato l’app Jira dell’editore. Se
  manca, la pagina lo segnala; un’app propria o un token API non sono né
  necessari né previsti.
- Ogni organizzazione ha esattamente una connessione Jira e un sito.

## Collegare e scegliere il sito

1. Faccia clic su **Collegare con Jira** e acceda ad Atlassian. WorkDiary
   chiede solo l’accesso in lettura a progetti e ticket.
2. Tornato sulla pagina, scelga il sito in **Scegliere Sito** e faccia clic su
   **Applicare**. Vengono proposti solo i siti leggibili con questo accesso.
3. Finché esistono associazioni di progetti, il sito non può essere cambiato:
   le attività importate restano chiaramente legate al loro sito.

## Associare progetti e controllare l’anteprima

1. **Associare un progetto** apre una finestra di dialogo: scelga il
   **Progetto Jira** e il **Progetto WorkDiary** a cui appartengono le
   attività. Ogni progetto Jira ha esattamente una destinazione.
2. Si apre quindi l’**Anteprima**. Mostra i ticket aperti e cosa ne farebbe la
   prima sincronizzazione: **Verrà creata**, **Già collegata**, **Altra
   destinazione**, **Caso da verificare** o **Gestita altrove**. L’anteprima
   non salva nulla.
3. Se il progetto di destinazione contiene già un’attività adatta, la colleghi
   invece di crearne una nuova: con lo stesso titolo tramite **Collegare
   l’omonima**, altrimenti nella sezione **Collegare un’attività esistente**. I
   campi divergenti vanno nella posta in arrivo per la verifica; i campi vuoti
   vengono compilati da Jira.
4. **Confermare l’importazione** abilita l’associazione. Solo allora la
   sincronizzazione crea i ticket aperti come attività. I ticket già
   completati non vengono mai creati.

## Cosa viene importato

- **Titolo** dal riepilogo, **descrizione** come testo semplice (immagini,
  tabelle e altri contenuti non rappresentabili come testo generano un caso da
  verificare; una descrizione esistente resta invariata), **scadenza** come
  data.
- **Stato** tramite la categoria di stato di Jira: da fare → **Aperto**, in
  corso → **In corso**, completato → **Completato**. I nomi degli stati nel Suo
  Jira non contano.
- Cliente, tempi, tariffe, budget e incaricati restano gestiti da WorkDiary.
- Jira non riapre un’attività che Lei ha completato in WorkDiary. Solo una
  chiusura arrivata da Jira viene riaperta con Jira.
- Nella finestra dell’attività, **Apri in Jira** porta al ticket.

## Quando avviene la sincronizzazione

- Ogni 15 minuti WorkDiary recupera i ticket modificati dall’ultima
  esecuzione, una volta al giorno tutti i ticket dei progetti associati.
  Modifichi la cadenza in **Job pianificati**.
- **Sincronizzare ora** nel menu ⋯ avvia subito la stessa sincronizzazione.
- Se un’esecuzione si interrompe (ad esempio per un guasto di Jira), risulta
  incompleta: l’associazione mostra **incompleta** e l’esecuzione successiva
  riprende dall’ultimo stato completo.

## Conflitti e casi da verificare

- Se un campo è stato modificato in modo diverso in WorkDiary e in Jira, nella
  posta in arrivo compare un conflitto in cui Lei decide quale versione vale.
  Nulla viene riscritto in Jira.
- Casi da verificare compaiono tra l’altro quando un ticket non è più visibile
  in Jira (la Sua attività resta invariata, nulla viene eliminato), quando la
  sua attività si trova in un altro progetto WorkDiary (nulla viene spostato) o
  quando arriva uno stato sconosciuto.
- Se la **Titolarità dei dati** assegna le attività a un altro sistema,
  WorkDiary non importa nulla e crea un caso per ogni ticket.
- **Casi da verificare** nel menu ⋯ apre la posta in arrivo filtrata su Jira.

## Mettere in pausa, rimuovere, scollegare

- **Mettere in pausa** e **Riprendere** nella riga di un’associazione fermano
  e riavviano la sua sincronizzazione. **Rimuovere l’associazione** scioglie
  l’associazione; attività, collegamenti e tempi importati restano.
- **Scollegare** elimina l’accesso e mette in pausa tutte le associazioni. Se
  in seguito si ricollega allo stesso sito, riprendono.

## Errori frequenti

- «Jira non è configurato per questa installazione»: manca la registrazione
  dell’app; si rivolga al gestore.
- Nessun sito da scegliere: l’account collegato non ha accesso in lettura a
  Jira su alcun sito.
- La connessione segnala un accesso rifiutato: l’autorizzazione è stata
  revocata in Atlassian o è scaduta. Ricolleghi l’account.
- Non viene creato nulla: l’associazione non è ancora confermata, è in pausa
  oppure i ticket sono già completati.
