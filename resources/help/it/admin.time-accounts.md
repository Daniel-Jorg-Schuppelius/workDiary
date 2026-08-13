---
title: "Conti del tempo (amministrazione)"
topic: admin.time-accounts
version: 1
audience: [admin]
related:
    - time-accounts.overview
---

I conti del tempo aggiuntivi trasformano dati esistenti in conti gestiti:
contatori di turni notturni, conti di risparmio tempo libero, raccoglitori
di indennità. Orario flessibile e ferie restano conti separati e non
vengono duplicati qui.

Per ogni conto si definiscono l'unità (minuti, giorni, quantità), soglie
semaforiche opzionali e la regola di riporto: cumulativa o con tetto alla
chiusura mensile. Le regole di registrazione definiscono la fonte in modo
dichiarativo: modelli di tipo salariale dal motore delle regole, presenza
netta, giorni di assenza, un contatore per tipo di turno o quantità da
posizioni esterne importate; un fattore pondera (ad es. 1,25 per «l'ora
notturna conta 1:1,25»).

L'esecuzione giornaliera registra in modo idempotente; il giornale è
immutabile: le correzioni sono storni, le registrazioni manuali richiedono
una motivazione e vengono verificate. Facoltativamente il saldo appare
nella risposta di stato del terminale.
