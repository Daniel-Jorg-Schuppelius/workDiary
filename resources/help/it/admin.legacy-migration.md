---
title: "Migrazione legacy"
topic: admin.legacy-migration
version: 1
audience:
    - admin
related:
    - admin.import
    - admin.data-transfer
    - admin.handbook
---

La migrazione legacy trasferisce i dati dal sistema precedente a
WorkDiary e mostra lo stato di ripresa per area dati (**utenti**, voci
del diario, turni di reperibilità, interventi di emergenza).
Presupposto è una connessione database configurata al vecchio sistema;
se non è raggiungibile, l'area appare come «non configurata».
L'importazione si avvia per area ed esegue in background il comando
`legacy:import`; i record già importati sono collegati tramite un
identificativo legacy, quindi le esecuzioni ripetute non creano
duplicati. La scrittura dipende dalla configurazione
(`legacy_write_enabled`) e l'accesso richiede il permesso di
consultazione dei log di audit.
