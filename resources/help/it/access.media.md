---
title: "Supporti di accesso"
topic: access.media
version: 1
audience: []
modules:
    - module.fuhrpark
related:
    - assets.fleet
---

**Transponder, tessere e codici** come inventario gestito — l’estensione
della consegna fisica delle chiavi. Ogni supporto ha in ogni momento
**esattamente uno stato** (A magazzino / Consegnato / Smarrito / Bloccato /
Dismesso) e una collocazione documentata.

## Principi

- **Il numero del supporto è salvato solo come hash** — restano visibili le
  ultime quattro cifre. Il testo in chiaro è noto solo alla creazione.
- **Il detentore è un utente O una persona esterna** (nome + azienda) —
  un’impresa di pulizie non ha un account collaboratore.
- **workDiary non pilota alcun impianto di accesso.** Lo stato amministrativo
  qui e lo stato dell’impianto là sono tenuti insieme dal compito di blocco.

## Smarrimento e blocco

Una segnalazione di smarrimento imposta lo stato **Smarrito** e crea
obbligatoriamente un **compito di blocco** («Bloccare il supporto …1234
nell’impianto X», scadenza due giorni). Solo chi ha eseguito il blocco
nell’impianto lo conferma — il supporto diventa **Bloccato** e il compito
fatto. Smarrito e bloccato sono stati volutamente separati: proprio questa
lacuna deve essere visibile, perché lì il supporto è un rischio.

## Consegna e restituzione

Ogni consegna (consegna/restituzione) finisce nella **cronologia** del
supporto — con detentore, momento, restituzione attesa e stato. Un supporto
consegnato non può essere dismesso — prima riprenderlo.
