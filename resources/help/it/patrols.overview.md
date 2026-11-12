---
title: "Ronde di sorveglianza"
topic: patrols.overview
version: 2
keywords:
    - giro di ispezione
    - controllo ronde
    - punti di controllo
    - scansionare un punto
    - prova di passaggio
    - vigilanza
    - guardia giurata
    - servizio di sicurezza
    - codice QR
    - punto mancato
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

## Gestire percorsi e punti di controllo

**Modifica** cambia denominazione, oggetto e **responsabile** del percorso. Un
percorso si può **disattivare**: una ronda in corso può terminare, non se ne
possono avviare di nuove. I punti di controllo si possono modificare (luogo,
previsto, tolleranza; le scansioni registrate mantengono la loro valutazione) e
**disattivare**: da quel momento non sono più previsti e non si possono più
scansionare. Le ronde precedenti restano invariate: una ronda iniziata prima
della disattivazione continua a prevedere il punto.

## Escalation

Il punto aperto nasce **subito allo scostamento** — con la prima scansione
fuori finestra o appena un punto scaduto viene saltato — e va al responsabile
del percorso, in mancanza al responsabile del team di chi svolge la ronda. Per
ogni ronda c'è un solo punto aperto, aggiornato fino alla fine. Se una ronda è
ancora in corso dopo la durata prevista più la tolleranza dell'ultimo punto, il
controllo delle scadenze la segnala una volta come **non terminata**; la
centrale può quindi interromperla con motivazione.
