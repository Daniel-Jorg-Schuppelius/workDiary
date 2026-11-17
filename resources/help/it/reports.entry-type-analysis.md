---
title: "Analisi per tipo di incarico"
topic: reports.entry-type-analysis
version: 5
keywords:
    - previsto vs effettivo
    - scostamento dal pianificato
    - tempo di lavorazione
    - durata media
    - tasso di rilavorazione
    - sforamento ore
    - confronto tipi di intervento
    - cause principali
    - analisi degli scostamenti
    - stima dei tempi
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

L'analisi per tipo di incarico confronta durate pianificate ed effettive
per tipo di voce (es. manutenzione, guasto, migrazione) e mostra la
**durata media di lavorazione** con la dispersione, lo **scostamento
piano/consuntivo** in percentuale, la **quota di rilavorazione** e le
**cause principali** se classificate. Così individua i tipi di incarico
con ipotesi di pianificazione irrealistiche o con sovraccarico
sistematico del team — entrambi segnali di esigenze di formazione o di
revisione dei prezzi.

La durata pianificata di un incarico è la sua **Durata prevista (HH:MM)**; se è
vuota, conta la durata dell’intervento di un ordine pianificato, altrimenti la durata della finestra oraria o dell’appuntamento.

La colonna **Cause principali** indica, per tipo di ordine, le tre classificazioni più frequenti tra il motivo di rilavorazione dei tempi registrati e la causa principale dei reclami sull’ordine. Ogni ordine conta una volta; un clic mostra tempi e reclami interessati. Anche l’esportazione CSV contiene la colonna.

## Tempo di attraversamento per tipo di ordine

La sezione **Tempo di attraversamento per tipo di ordine** mostra, per gli
ordini chiusi nel periodo, la mediana dei giorni dalla creazione alla chiusura
e il numero di chiusure. Gli ordini annullati non contano. Il filtro per
persona qui volutamente non si applica: si misura per ordine, mai per persona.
La vista di direzione mostra lo stesso valore per i tipi di ordine più
frequenti degli ultimi dodici mesi e rimanda qui.
