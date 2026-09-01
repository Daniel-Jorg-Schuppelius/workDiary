---
title: "Radar dei bandi di gara"
topic: tenders.radar
version: 1
audience: []
modules:
    - module.applications
related:
    - applications.overview
---

Il radar analizza gli **avvisi di gara pubblici federali tedeschi** alla
ricerca di bandi adatti alla propria azienda. La fonte è il servizio ufficiale
degli avvisi (oeffentlichevergabe.de), che pubblica tutti gli avvisi
obbligatori come dati aperti con licenza CC0 — senza registrazione né
credenziali di portale.

**I profili di ricerca** stabiliscono cosa cercare. Due sistemi di codici
reggono la ricerca: **CPV** indica *cosa* viene acquistato, **NUTS** indica
*dove*. Entrambi sono gerarchici, quindi bastano i prefissi — `45` copre tutti
i lavori edili, `DEA` l'intera Renania Settentrionale-Vestfalia. Le parole
chiave cercano inoltre in titolo, descrizione e stazione appaltante; **le
parole di esclusione pesano di più**: una corrispondenza lì scarta l'avviso
anche se tutto il resto combacia. Gli avvisi senza importo indicato non vengono
mai esclusi dai limiti di valore — altrimenti si perderebbe tutto ciò che non
dichiara il proprio importo.

**Il recupero è quotidiano e riguarda il giorno precedente.** Una giornata di
pubblicazione è completa solo il giorno dopo; recuperare oggi lascerebbe
lacune. Gli avvisi rettificati arrivano come nuova versione, la precedente
resta.

**La casella dei risultati propone, non decide.** Ciò che non serve viene
nascosto e conservato come prova; ciò che serve diventa una pratica di gara con
titolo, stazione appaltante, CPV, regione, termine e fonte precompilati.
**Verificare poi il tipo di procedura e la soglia** — la fonte aperta nomina la
procedura solo a grandi linee, e da essa non si possono ricavare con certezza
né il tipo di procedura tedesco né la situazione rispetto alle soglie.
