---
title: "Sistema precedente (Legacy)"
topic: legacy.overview
version: 2
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
la **gestione utenti** del sistema precedente e un accesso **call
center** dedicato. Le funzioni di lettura sono sempre disponibili; le
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
persone li vedono gli amministratori del sistema precedente e il ruolo
**Contabilità**; tutti gli altri vedono solo i propri. Il login del call center
porta alla stessa pagina.

**Dipendente** si trova nel menu di amministrazione (icona **Amministrazione**
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

**Autorizzazione:** la pagina **Dipendente** è accessibile agli amministratori
del sistema precedente e alla gestione della piattaforma; il solo ruolo di
amministratore dell'organizzazione non basta.
