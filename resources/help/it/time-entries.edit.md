---
title: "Modificare una registrazione di tempo"
topic: time-entries.edit
version: 2
keywords:
    - correggere le ore
    - cambiare registrazione
    - ore sbagliate
    - modificare inizio e fine
    - modificare pausa
    - cambiare progetto
    - richiesta di correzione
    - voce bloccata
    - cronologia modifiche
    - sistemare orario
    - tempo amministrativo
    - registrare tempo interno
audience: []
related:
    - time-entries.start
    - reports.customer-analysis
---

Faccia clic su una riga dell'elenco di rilevazione tempi per modificare la
registrazione; ogni modifica viene tracciata nel registro di audit con
persona, momento e valore precedente. Le registrazioni **già approvate**
sono bloccate: per correzioni successive usi le **richieste di correzione**.
Non cambi mai solo la fine di un turno, ma adegui **sempre** inizio, fine
e pausa come insieme, altrimenti i report diventano incoerenti; il cambio di
progetto è consentito finché la vecchia assegnazione non è già stata
fatturata.

## Tempo amministrativo

Il tempo amministrativo è orario di lavoro senza progetto: riunioni,
formazione, lavori interni, tempo di trasferta, pause e altre attività. Lo
registra sempre per sé stesso – la registrazione viene attribuita al Suo
account. La registrazione per altre persone non è prevista qui.

**Dove iniziare:**

- Nella barra laterale tramite **Nuovo …** → **Operatività quotidiana** →
  **Tempo amministrativo**. La data è preimpostata su oggi.
- Nella vista del giorno (**Operatività quotidiana** → **Inserimento** →
  **Oggi**) tramite il pulsante **Tempo amministrativo** in alto a destra. La
  data è preimpostata sul giorno visualizzato – anche su un giorno precedente,
  se vi è tornato con **Giorno precedente**.

**Campi della finestra «Registra tempo amministrativo»:**

- **Data** e **Durata (minuti)** sono obbligatori. La durata è compresa tra
  1 e 1440 minuti; sono preimpostati 30 minuti.
- **Tipo di attività** (obbligatorio): **Amministrazione** (predefinito),
  **Riunione**, **Formazione**, **Interno**, **Trasferta**, **Pausa** o
  **Altro**.
- **Categoria (facoltativa)**: una delle categorie di attività attive della
  Sua organizzazione; l'elenco mostra il tipo di attività di ogni categoria.
- **Periodo (facoltativo)**: **Inizio (ora)** e **Fine (ora)**. Una fine senza
  inizio viene rifiutata. Se la fine è precedente all'inizio, vale per il
  giorno successivo – così registra orari oltre la mezzanotte. Se inizio e
  fine sono indicati entrambi, l'applicazione calcola la durata da questi e
  sostituisce il numero di minuti inserito.
- **Descrizione** (fino a 500 caratteri) ed **Etichette**.
- Se per la data preimpostata esiste già una Sua timbratura, la
  registrazione viene collegata a essa. La finestra mostra allora
  l'indicazione «Viene collegato alla timbratura (dalle …)».

Dopo **Registra** torna alla vista del giorno della data scelta. La
registrazione compare lì sotto **Registrazioni ore** con il suo tipo di
attività e, se scelta, la categoria.

**Differenze rispetto alla rilevazione tempi su progetto:**

- Non esiste un campo progetto; il tempo viene classificato tramite tipo di
  attività e categoria.
- Inizio e fine sono facoltativi – basta una durata.
- Il tipo di attività è limitato ai tipi senza riferimento a un progetto
  elencati sopra.

**Modificare ed eliminare:** nella vista **Oggi** l'icona della matita
(**Modifica**) nella riga di un tempo amministrativo apre la finestra
**Modifica tempo amministrativo**. Nella finestra **Modifica tempo amministrativo**
modifica gli stessi campi, scrive **Commenti** ed elimina la registrazione con
**Elimina** (dopo una richiesta di conferma). Dopo il salvataggio o
l'eliminazione si apre la vista del giorno della data della registrazione.
Può modificare ed eliminare solo le proprie registrazioni e solo finché non
sono bloccate. Una registrazione è bloccata se

- la finestra di correzione è scaduta (per impostazione predefinita 7 giorni
  dopo il giorno della registrazione),
- il mese è già stato approvato per Lei,
- il foglio ore collegato è firmato o bloccato, oppure
- la registrazione è già stata esportata.

La finestra indica allora il motivo; i commenti restano possibili.

**Autorizzazione:** ogni persona connessa può registrare tempo amministrativo
per sé. Il ruolo **Amministratore** può modificare ed eliminare anche le
registrazioni di altre persone e quelle bloccate; la finestra segnala allora
che sta modificando come admin.
