---
title: "Dispacciamento e avvisi di conflitto"
topic: dispatch.overview
version: 2
keywords:
    - pianificazione interventi
    - assegnare un intervento
    - pianificare un tecnico
    - doppia prenotazione
    - sovrapposizione
    - riposo
    - orario massimo di lavoro
    - prenotare un veicolo
    - prenotazione veicolo
    - conferma appuntamento
audience: []
related:
    - diary-entries.edit
    - planning.shifts
    - assets.fleet
---

La pianificazione stabilisce **chi esegue quale ordine e quando** — a
integrazione della macchina a stati degli ordini. Ogni ordine ha uno
**Stato di pianificazione**:

- **Non pianificato**: né programmato né assegnato.
- **Pianificato**: programmato o assegnato a un dipendente.
- **Confermato**: l'assegnazione è stata confermata in modo vincolante.
- **In viaggio**: l'intervento è in corso.
- **Completato**: l'ordine è concluso.

## Avvisi di conflitto prima della conferma

Prima della conferma dell'appuntamento, WorkDiary verifica l'assegnazione
pianificata rispetto alle regole esistenti sull'orario di lavoro e sulla
disponibilità (sovrapposizione con altri turni o ordini, riposo, orario
massimo giornaliero/settimanale, ferie e assenze). Esistono due livelli di
gravità:

- I **conflitti bloccanti** impediscono la conferma. Possono essere
  scavalcati consapevolmente solo con una **motivazione documentata**; lo
  scavalcamento viene registrato a prova di revisione.
- Gli **avvisi** sono indicazioni e non bloccano.

## Prenotazione veicolo

Dall'ordine è possibile prenotare un veicolo per una fascia oraria. Se il
veicolo è già prenotato nel periodo desiderato, il sistema impedisce la
doppia prenotazione. Le prenotazioni di ciascun veicolo si possono
consultare nell'elenco delle prenotazioni e annullare.
