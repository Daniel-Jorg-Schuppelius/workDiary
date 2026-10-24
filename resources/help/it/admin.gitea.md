---
title: "Importazione di attività da Gitea/Forgejo"
topic: admin.gitea
version: 1
keywords:
    - Gitea
    - Forgejo
    - importare ticket
    - repository
    - self-hosted
    - token di accesso
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

La pagina **Gitea/Forgejo** importa i ticket di repository selezionati della
Sua istanza Gitea o Forgejo self-hosted come attività nei progetti WorkDiary.
Così registra i tempi sul lavoro pianificato lì. L’importazione legge soltanto:
WorkDiary non scrive nulla nell’istanza. Trova la pagina nel menu di sistema
(l’ingranaggio **Sistema**) sotto **Plugin** → **Gitea/Forgejo**, non appena il
plugin è attivo.

## Configurare l’accesso

1. In Gitea o Forgejo crei in **Impostazioni** → **Applicazioni** un token di
   accesso con gli ambiti **read:user**, **read:repository** e **read:issue**.
   Un token limitato a repository selezionati vede solo quelli, anche nella
   finestra.
2. Sulla pagina apra **Credenziali** nel menu ⋯ e inserisca l’**URL di base**
   (ad es. https://git.example.com, senza /api/v1) e il **Token di accesso**. Se
   l’istanza si trova nella propria rete, attivi anche **Consentire indirizzi
   privati**: ha effetto solo se il gestore lo consente.
3. Faccia clic su **Verificare la connessione**. WorkDiary accede all’istanza e
   mostra istanza, indirizzo e account.

Finché esistono associazioni, non è possibile inserire un’altra istanza; le
attività importate restano chiaramente legate alla loro istanza.

## Associare repository e controllare l’anteprima

1. **Associare un progetto** apre una finestra con i repository accessibili al
   token e i progetti WorkDiary. Ogni repository ha esattamente una
   destinazione.
2. L’**Anteprima** mostra i ticket aperti e cosa ne farebbe la prima
   sincronizzazione. Non salva nulla.
3. Colleghi attività esistenti del progetto di destinazione tramite
   **Collegare l’omonima** o **Collegare un’attività esistente**; i campi
   divergenti vanno nella posta in arrivo per la verifica.
4. **Confermare l’importazione** abilita l’associazione. Solo allora la
   sincronizzazione crea i ticket aperti come attività; quelli chiusi mai.

## Cosa viene importato

- **Titolo**, **descrizione** come testo e **scadenza** come data, così come
  la mostra l’istanza.
- **Stato**: chiuso → **Completato**, aperto → **Aperto**. Se un’attività è **In
  corso** in WorkDiary, resta tale.
- Le pull request non vengono importate. Se un repository viene rinominato,
  l’importazione continua.
- Nella finestra dell’attività, **Apri in Gitea/Forgejo** porta al ticket.

## Quando avviene la sincronizzazione

Ogni 15 minuti WorkDiary recupera i ticket modificati dall’ultima esecuzione, una
volta al giorno tutti. Modifichi la cadenza in **Job pianificati**;
**Sincronizzare ora** nel menu ⋯ avvia subito. Un’esecuzione interrotta risulta
**incompleta**; la successiva riprende dall’ultimo stato completo.

## Conflitti e casi da verificare

Modifiche diverse su entrambi i lati generano un conflitto nella posta in
arrivo; nulla viene riscritto nell’istanza. Se un ticket collegato non è più
visibile, la Sua attività resta invariata e la posta in arrivo segnala un caso da
verificare. Se la **Titolarità dei dati** assegna le attività a un altro sistema,
WorkDiary non importa nulla. **Casi da verificare** nel menu ⋯ apre la posta in
arrivo filtrata su Gitea/Forgejo.

## Mettere in pausa, rimuovere, scollegare

**Mettere in pausa** e **Riprendere** fermano e riavviano la sincronizzazione di
un’associazione; **Rimuovere l’associazione** conserva attività e tempi
importati. **Scollegare** ferma la sincronizzazione e mette in pausa tutte le
associazioni. Revochi il token in Gitea/Forgejo o lo elimini in **Credenziali**;
una nuova verifica della stessa istanza riprende le associazioni.

## Errori frequenti

- La verifica segnala un indirizzo interno: l’URL di base punta alla propria
  rete, ma **Consentire indirizzi privati** è disattivato o non consentito dal
  gestore.
- La verifica fallisce con 401: il token è errato, scaduto o senza accesso in
  lettura.
- Un repository manca nella finestra: il token non lo vede, ad esempio perché
  vale solo per repository selezionati.
- La verifica fallisce con 403: al token manca uno degli ambiti **read:user**,
  **read:repository** o **read:issue**.
