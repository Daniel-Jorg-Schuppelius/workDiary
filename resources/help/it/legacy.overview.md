---
title: "Sistema precedente (Legacy)"
topic: legacy.overview
version: 5
keywords:
    - vecchio sistema
    - dati storici
    - migrazione dati
    - reperibilità
    - servizio di emergenza
    - accesso call center
    - archivio storico
    - vecchie voci di diario
    - utenti del vecchio sistema
    - modalità legacy
    - centrale
    - portale assistenza
    - clienti del portale
    - ordini clienti del vecchio sistema
    - avviso e-mail
related:
    - auth.login
    - admin.tenants
---

L'area Legacy è un ponte verso il sistema precedente: rende ancora
disponibili dati e funzioni della vecchia applicazione finché non sono
completamente trasferiti in WorkDiary. L'accesso è riservato agli utenti
con un identificativo del sistema precedente e agli amministratori.
Comprende il **diario** (vista settimanale e gestione delle voci), il
**servizio di emergenza e reperibilità**, l'**archivio** in sola lettura,
la **gestione utenti** del sistema precedente, un accesso **call
center** dedicato e il **portale assistenza** con ordini e accessi dei clienti. Le funzioni di lettura sono sempre disponibili; le
azioni di scrittura e il cambio password sono attive solo se l'accesso
in scrittura al sistema precedente è abilitato. Gli amministratori
dispongono inoltre di un cruscotto di migrazione per importare i dati.

## Centrale e Dipendente

In **Modalità legacy** – attivabile in **Impostazioni** nell'intestazione se Lei
ha accesso a entrambe le aree – la navigazione principale mostra **Vista
settimanale**, **Lista di lavoro** e **Centrale**.

**Centrale** è il quadro della situazione del sistema precedente:

- Riquadri **Problemi**, **Aperto**, **Confermato** e **Completato (7g)** oltre
  a **Scaduto**, **In scadenza oggi** e **Prossimi 7g**; un clic apre la lista
  di lavoro con il filtro corrispondente.
- Il **Piano settimanale** con **Servizio di emergenza** e **Reperibilità**, a
  partire da ieri; si sfoglia con **Settimana precedente**, **Settimana
  prossima** e **Settimana attuale**.
- **Fine settimana e festivi**, **Nuove voci (14 giorni)**, **Principali
  responsabili (aperti)**, **Prossimi giorni festivi (30 giorni)** e **Notifiche
  aperte**.

I piani di servizio sono visibili a tutti. I dati del diario di tutte le
persone li vedono gli amministratori del sistema precedente, la gestione della
piattaforma e il ruolo **Contabilità**; tutti gli altri vedono solo i propri. Il
login del call center porta alla stessa pagina.

**Nuova voce** è disponibile per chiunque abbia l'account utente collegato a un
account del sistema precedente; solo gli amministratori del sistema precedente
creano voci per altre persone. Archiviazione, servizio di emergenza e
reperibilità sono gestiti dagli amministratori del sistema precedente e dalla
gestione della piattaforma.

**Dipendenti** si trova nel menu di amministrazione (icona **Amministrazione**
nell'intestazione) sotto **Personale** ed elenca gli utenti del sistema
precedente con **Nome** e **E-mail**:

- **Nuovo dipendente** crea una persona con **Nome**, **E-mail** e
  **Password**; in modifica la password resta invariata se il campo rimane
  vuoto.
- I primi tre account del sistema precedente (amministratori) non compaiono e
  qui non si possono modificare.
- **Elimina** è possibile solo finché per la persona non esistono voci di
  diario, di servizio di emergenza o di reperibilità.
- Creare, modificare ed eliminare richiedono l'accesso in scrittura al sistema
  precedente.

**Autorizzazione:** la pagina **Dipendenti** è accessibile agli amministratori
del sistema precedente e alla gestione della piattaforma; il solo ruolo di
amministratore dell'organizzazione non basta.

## Portale assistenza

**Portale assistenza** nella navigazione principale della modalità legacy mostra
gli ordini che i clienti hanno effettuato nel portale clienti del vecchio
sistema:

- Si filtra per **Anno** (è preselezionato l'anno più recente con ordini),
  **Cliente** e testo di ricerca; le tessere **Nuovo**, **Visionato**,
  **Appuntamento fissato**, **Appuntamento scaduto**, **Completato** e
  **Archiviato** filtrano l'elenco.
- **Modifica** gestisce cliente, telefono, sito, descrizione, **Appuntamento**,
  **Tecnico**, **Messaggio per il cliente**, **Nota interna**, **Durata** e
  **Stato**. Un nuovo appuntamento imposta lo stato su **Appuntamento fissato**,
  salvo che sia scelto completato o archiviato. Il cliente vede il messaggio,
  non la nota.
- **Crea ordine** registra un ordine per un cliente, ad esempio dopo una
  telefonata.
- **Avviso via e-mail** attiva la notifica per i nuovi ordini dei clienti e
  stabilisce il destinatario.
- **Archivio** elenca gli ordini archiviati; **Archivia** vi sposta gli ordini
  con stato **Archiviato** datati prima di un giorno di riferimento. In seguito
  i clienti non li vedono più.

**Clienti del portale** nel menu di gestione amministra gli accessi dei clienti
con **Azienda**, **Nome utente** e **Password** (da 4 a 10 caratteri). I clienti
con ordini in corso non si possono eliminare.

L'indirizzo di accesso per i Suoi clienti è indicato in alto nella pagina
**Clienti del portale**; **Copia** lo copia negli appunti.

I clienti accedono dalla pagina di accesso propria del portale clienti, vedono
solo i propri ordini con appuntamento e risposte, effettuano nuovi ordini e
cambiano la password. Se l'accesso in scrittura al vecchio sistema è
disattivato, il portale è in sola lettura.

**Autorizzazione:** portale assistenza e clienti del portale sono aperti agli
amministratori del vecchio sistema e alla gestione della piattaforma.
