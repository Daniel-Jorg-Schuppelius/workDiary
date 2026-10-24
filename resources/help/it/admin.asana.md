---
title: "Importazione di attività da Asana"
topic: admin.asana
version: 1
keywords:
    - Asana
    - importare attività
    - workspace
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

La pagina **Asana** importa le attività di progetti Asana selezionati nei Suoi
progetti WorkDiary. Così registra i tempi sul lavoro pianificato in Asana e lo
fattura come di consueto. L’importazione legge soltanto: WorkDiary non scrive
nulla in Asana. Trova la pagina nel menu di sistema (l’ingranaggio
**Sistema**) sotto **Plugin** → **Asana**, non appena il plugin è attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce Asana.
- Il modulo Kanban è concesso in licenza; la pagina è aperta agli
  amministratori.
- Il gestore della Sua installazione ha configurato l’app Asana dell’editore.
  Se manca, la pagina lo segnala; un token di accesso personale non è né
  necessario né previsto.
- Ogni organizzazione ha esattamente una connessione Asana e un workspace.

## Collegare e scegliere il workspace

1. Faccia clic su **Collegare con Asana** e acceda ad Asana. WorkDiary chiede
   solo l’accesso in lettura ad attività, progetti, workspace e al Suo
   account.
2. Tornato sulla pagina, scelga il workspace in **Scegliere Workspace** e
   faccia clic su **Applicare**.
3. Finché esistono associazioni di progetti, il workspace non può essere
   cambiato.

## Associare progetti e controllare l’anteprima

1. **Associare un progetto** apre una finestra di dialogo: scelga il
   **Progetto Asana** e il **Progetto WorkDiary** a cui appartengono le
   attività.
2. L’**Anteprima** mostra le attività aperte e cosa ne farebbe la prima
   sincronizzazione. Non salva nulla.
3. Colleghi attività esistenti del progetto di destinazione tramite
   **Collegare l’omonima** o la sezione **Collegare un’attività esistente**. I
   campi divergenti vanno nella posta in arrivo per la verifica.
4. **Confermare l’importazione** abilita l’associazione. Solo allora la
   sincronizzazione crea le attività aperte. Le attività completate non vengono
   mai create.

## Cosa viene importato

- **Titolo**, **note** come descrizione e **scadenza** come data. Se
  un’attività Asana ha un orario di scadenza, la data vale nel fuso orario
  della Sua organizzazione.
- **Stato**: completata in Asana → **Completato**, altrimenti **Aperto**. Se
  l’attività è **In corso** in WorkDiary, resta tale: Asana non conosce uno
  stato di avanzamento.
- Un’attività presente in più progetti associati con la stessa destinazione
  viene creata una sola volta. Se i progetti puntano a destinazioni diverse,
  WorkDiary non la crea e segnala un caso da verificare.
- Asana non riapre un’attività che Lei ha completato in WorkDiary.
- Nella finestra dell’attività, **Apri in Asana** porta all’attività.

## Quando avviene la sincronizzazione

- Ogni 15 minuti WorkDiary legge le attività aperte e quelle completate
  dall’ultima esecuzione, una volta al giorno tutte. Modifichi la cadenza in
  **Job pianificati**; **Sincronizzare ora** nel menu ⋯ avvia subito.
- Se un’esecuzione si interrompe, risulta incompleta e la successiva riprende
  dall’ultimo stato completo.

## Conflitti e casi da verificare

- Modifiche diverse su entrambi i lati generano un conflitto nella posta in
  arrivo; nulla viene riscritto in Asana.
- Se un’attività collegata non è più visibile in Asana, la Sua attività resta
  invariata e la posta in arrivo segnala un caso da verificare.
- Se la **Titolarità dei dati** assegna le attività a un altro sistema,
  WorkDiary non importa nulla.
- **Casi da verificare** nel menu ⋯ apre la posta in arrivo filtrata su Asana.

## Mettere in pausa, rimuovere, scollegare

- **Mettere in pausa** e **Riprendere** fermano e riavviano la
  sincronizzazione di un’associazione; **Rimuovere l’associazione** conserva
  attività e tempi importati.
- **Scollegare** revoca l’autorizzazione in Asana, elimina l’accesso e mette
  in pausa tutte le associazioni. Ricollegate allo stesso workspace,
  riprendono.

## Errori frequenti

- «Asana non è configurato per questa installazione»: manca la registrazione
  dell’app; si rivolga al gestore.
- La connessione segnala un accesso rifiutato: l’autorizzazione è stata
  ritirata in Asana. Ricolleghi l’account.
- Non viene creato nulla: l’associazione non è ancora confermata, è in pausa
  oppure le attività sono già completate.
