---
title: "Libro cassa"
topic: finance.cashbook
version: 1
audience:
    - admin
modules:
    - module.kasse
related:
    - invoices.manage
---

Il **libro cassa** documenta entrate e uscite in contanti in conformità GoBD
(MVP-414). workDiary non è un sistema di cassa (nessun POS, nessun obbligo TSE).

- **Immutabile**: le registrazioni ricevono un numero progressivo e una catena
  di hash; modificare ed eliminare è tecnicamente impossibile.
- **Storno invece di cancellazione**: le correzioni sono controregistrazioni
  con motivo obbligatorio; l'originale resta.
- **Chiusura giornaliera**: conteggio con previsto/contato/differenza; dopo
  tutte le registrazioni fino alla data di chiusura sono bloccate.
- **Pagamento in contanti**: un'entrata può referenziare una fattura — la
  copertura totale la segna come pagata.
- Il libro cassa fa parte dell'**export GoBD Z3** (kassenbuch.csv).
