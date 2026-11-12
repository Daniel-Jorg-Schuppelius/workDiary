---
title: "Collegare la prenotazione appuntamenti (Calendly)"
topic: admin.calendly
version: 2
keywords:
    - prenotazione online
    - pagina di prenotazione
    - fissare appuntamenti
    - agenda online
    - appuntamenti clienti
    - disdetta appuntamento
    - spostamento appuntamento
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - appointments.inbox
---

L'integrazione porta nella gestione appuntamenti quelli che i clienti fissano
tramite una pagina di prenotazione.

**Collegamento:** Si effettua una volta per organizzazione e vale poi per tutte
le pagine dell'account collegato. Le credenziali sono salvate cifrate e dopo il
salvataggio non vengono più mostrate in chiaro.

**Prenotazioni in arrivo:** I nuovi appuntamenti arrivano prima nella **posta
in arrivo degli appuntamenti**, non direttamente nel calendario. Lì vengono
associati a un cliente: le corrispondenze univoche in automatico, i casi
dubbi restano in attesa della sua decisione. Solo dopo nasce l'appuntamento.

**Disdette e spostamenti** vengono recepiti se la pagina di prenotazione li
comunica. Un appuntamento già acquisito non viene cancellato in silenzio, ma
contrassegnato come disdetto.

**Limiti:** L'integrazione legge le prenotazioni; non crea pagine di
prenotazione e non modifica le disponibilità, che continua a gestire dove
amministra la pagina.

**Senza cliente corrispondente:** Una prenotazione il cui indirizzo non può
essere assegnato in modo univoco a un cliente compare nella **casella di
integrazione** come caso con destinazione «Cliente»: assegnare (suggerimenti per
nome), creare o scartare. In alternativa assegni la richiesta a un cliente o a
un lead nella casella degli appuntamenti.

**Assegnare gli host:** Gli host di Calendly vengono riconosciuti dal loro
indirizzo e-mail. Se l'indirizzo Calendly è diverso, lo assegni a un utente in
**Assegna utenti Calendly**; gli appuntamenti vanno quindi a quell'utente.

**Autorizzazioni:** Connessione, webhook, sincronizzazione, assegnazione degli
host e link di prenotazione spettano all'amministrazione dell'organizzazione.
Confermare e rifiutare le richieste di appuntamento può farlo chi gestisce la
pianificazione, in questa pagina come nella casella degli appuntamenti.

**Titolarità dei dati:** Le prenotazioni e le disdette da Calendly appartengono all’area calendario. Se un altro sistema gestisce il calendario, WorkDiary non crea né modifica alcuna richiesta di appuntamento; il caso resta nella casella delle integrazioni per essere acquisito una volta.
