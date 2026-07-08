---
title: "Lotti di registrazione DATEV"
topic: finance.datev-bookings
version: 1
audience: []
related:
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
    - glossary.core
---

Il **lotto di registrazione DATEV** trasmette fatture emesse, note di credito
e, facoltativamente, spese approvate di un periodo chiuso come file DATEV
verificabile (formato V700) al commercialista. WorkDiary non fa contabilità:
le fatture gestite da un programma di fatturazione esterno vengono escluse
automaticamente. Prima del primo export l'amministrazione configura la
**configurazione contabile** (numeri consulente/mandante, piano dei conti,
conti ricavi, chiavi d'imposta, numerazione debitori). Flusso: creare il
lotto come **bozza**, verificare l'anteprima con avvisi ed errori,
**finalizzare** (genera il file con hash SHA-256, il lotto diventa
immutabile) e scaricare il CSV. Creazione e download richiedono il ruolo
*Contabilità*.
