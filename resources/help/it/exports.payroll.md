---
title: "Esportazione tempi e consegna paghe"
topic: exports.payroll
version: 1
audience: []
related:
    - admin.surcharge-rules
    - finance.transfers
    - glossary.core
---

L'esportazione dei tempi trasmette i dati mensili approvati
all'elaborazione paghe in modo tracciabile e riproducibile. Flusso tipico:
i collaboratori inviano il mese («inviato»), il team lead lo approva
(«approvato») e dopo l'esportazione il mese viene **bloccato**;
l'esportazione passa per «in preparazione» → «pronta» e viene poi marcata
come «trasmessa» o «rifiutata». Il profilo produttivo è oggi solo
l'**esportazione CSV generica** (collaboratore, tipo di paga, quantità,
unità, periodo); il **profilo DATEV** è una preparazione vicina a LODAS,
non un formato DATEV certificato. L'esportazione è possibile solo se
tutte le approvazioni mensili interessate sono approvate o bloccate; ogni
esportazione porta un **hash SHA-256** riproducibile e dopo una correzione
nasce una **nuova esportazione**, mentre la vecchia viene marcata come
«sostituita».
