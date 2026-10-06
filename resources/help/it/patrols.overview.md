---
title: "Ronde di sorveglianza"
topic: patrols.overview
version: 1
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
---

Una **ronda** è un elenco ordinato di **punti di controllo** con finestre
previste relative all’avvio («punto 3: +20 min ± 10»). La **scansione attesta
punto e ora** — la prova affidabile verso i committenti (vigilanza, facility,
servizio invernale).

## Token

Ogni punto di controllo riceve un **token** (stampato sul tag/come QR). Si
salva solo l’hash; il testo in chiaro compare esattamente una volta — alla
creazione. **Un tag perso** si sostituisce con «riemettere il token»: nuovo
token, stesso percorso, il vecchio è subito privo di valore.

## Esecuzione

Avviare la ronda → scansionare i token (lo scanner-fotocamera digita come
tastiera, o inserimento manuale) → concludere. Al massimo una ronda per
percorso alla volta; le doppie scansioni contano una volta.

## Interruzione

Una ronda in corso può essere **interrotta** — solo con una **motivazione**.
In tal caso non conta come conclusa; i punti di controllo confermati restano
come prova, e il rapporto riporta l’interruzione con motivo, persona e ora. I
punti aperti passano alla centrale come **punto aperto**, come per uno
scostamento. Dopo, il percorso è di nuovo libero.

## Scostamenti

Punti mancati o scansioni fuori finestra vengono **mostrati, mai livellati** —
e la chiusura richiede allora una **motivazione**. Inoltre nasce un **punto
aperto** per la centrale (scadenza il giorno dopo) — l’escalation passa per il
sistema esistente, nessun canale separato.

Gli orari previsti sono **prova, non metrica di pressione**: niente dati di
posizione alla scansione né valutazioni personali di lungo periodo.
