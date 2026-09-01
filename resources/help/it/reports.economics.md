---
title: "Redditività"
topic: reports.economics
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

La vista di redditività (consuntivo) mostra per cliente e progetto nel
periodo scelto il margine di contribuzione: **ricavi** (tempi
fatturabili × tariffa + materiale + spese fatturabili, come proiezione —
la fattura vincolante resta nel sistema esterno), **costi** (tariffa di
costo interna × tempo + costi diretti di materiale e documenti) e
**margine di contribuzione** anche in percentuale. Include un
**ranking** top/flop 5 per progetto e cliente, il **tempo non
fatturabile** come proxy di rilavorazione e il confronto
**piano-consuntivo** rispetto ai budget di tempo e denaro del progetto.
Se per una parte dei tempi manca la tariffa di costo interna, entrano
con 0 € e il margine è troppo ottimistico (marcato con `*`).
Esportazione in CSV o PDF; dati finanziari a livello di organizzazione,
solo per utenti con diritto di lettura dei report.
