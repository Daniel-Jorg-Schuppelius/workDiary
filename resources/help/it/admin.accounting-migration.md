---
title: "Cambio di software contabile"
topic: admin.accounting-migration
version: 1
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

Il cambio di software contabile porta un'organizzazione da un sistema al
successivo in modo controllato (primo percorso supportato: Lexoffice →
orgaMAX). WorkDiary non copia ciecamente da un sistema all'altro: associa
entrambi i sistemi esterni agli stessi oggetti di business locali.

Passi: pianificare (aree dati + data di switch, un solo cambio per
organizzazione alla volta) → analisi come simulazione (non scrive in alcun
sistema esterno) → decidere singolarmente i record ambigui → esercizio
parallelo (i vecchi documenti vengono chiusi nel sistema di origine) →
commutazione (dalla data di switch i nuovi documenti nascono esclusivamente
nel sistema di destinazione; il push verso l'origine è bloccato
tecnicamente e la commutazione resta bloccata finché esistono conflitti) →
conclusione con protocollo CSV.

Principi: i documenti finalizzati **non** vengono mai ricostruiti nel
sistema di destinazione — restano consultabili come storico con numero,
stato e origine. Ogni passo è registrato in una catena di eventi a prova di
manomissione.
