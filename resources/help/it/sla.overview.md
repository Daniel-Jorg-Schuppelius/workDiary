---
title: "SLA, contratti e livelli di servizio"
topic: sla.overview
version: 4
keywords:
    - accordo sul livello di servizio
    - tempo di risposta
    - tempo di risoluzione
    - contratto di assistenza
    - violazione SLA
    - superamento scadenza
    - escalation
    - ticket in ritardo
    - tasso di rispetto
    - report SLA
audience: []
related:
    - glossary.core
---

I contratti SLA (Service Level Agreement) memorizzano, per cliente o come
**Contratto predefinito** per tutti i clienti, i tempi di reazione e di
risoluzione concordati per priorità (**Termini per priorità**),
facoltativamente con l’**Orario lavorativo** – senza di esso le scadenze
decorrono in tempo di calendario. Trova i contratti in **Service desk** →
**Contratti SLA**. Da questi valori obiettivo WorkDiary ricava lo stato
SLA di un ticket di assistenza e documenta i superamenti in modo
inalterabile.

## Stato SLA sul ticket

Ogni ticket di assistenza con una scadenza SLA mostra il proprio stato di
risoluzione come badge:

- **SLA nei tempi**: resta tempo sufficiente fino alla scadenza di
  risoluzione.
- **SLA a rischio**: il tempo residuo è al massimo il 20 % della
  scadenza complessiva.
- **SLA violato**: la scadenza è superata (oppure il ticket è stato
  confermato o risolto troppo tardi).
- **SLA rispettato**: il ticket è stato risolto in tempo.

I ticket senza scadenza mostrano «Nessuno SLA». La scadenza di reazione
viene valutata allo stesso modo e verificata alla prima conferma.

## Registro delle violazioni e rilevamento

Le scadenze superate vengono registrate in un registro delle violazioni
– esattamente una volta per ticket e tipo («Tempo di reazione» o «Tempo
di risoluzione»). Vengono rilevate:

1. durante il controllo automatico dei ticket aperti, che per
   impostazione predefinita viene eseguito ogni cinque minuti,
2. nei cambi di stato, quando la prima reazione o la risoluzione avviene
   troppo tardi.

A ogni violazione si può assegnare una **Causa** nell’**Elenco
violazioni** del report SLA e confermarla con **Conferma**; serve il
permesso **Confermare le violazioni SLA**.

## Escalation

Il controllo automatico notifica la persona assegnata per i ticket a
rischio e violati. Se l’evento resta irrisolto, WorkDiary esegue
l’escalation secondo le **Regole di notifica** dell’organizzazione verso
il ruolo di escalation lì impostato (per impostazione predefinita i capi
team). Inoltre WorkDiary applica i livelli registrati in **Escalation**
nel contratto SLA.

## Report SLA

Il **Report SLA** (**Report** → **Progetti e clienti** → **SLA**) mostra
per il periodo scelto i **Ticket con SLA**, il **Tasso di rispetto** e le
**Violazioni** – suddivise **Per tipo**, **Per priorità**, **Per
cliente** e **Per causa** – oltre a un **Elenco violazioni** con
collegamento al ticket e le **Quote di tempo incluso**. Il report è
esportabile in PDF, CSV ed Excel. Può consultarlo chi dispone del
permesso **Visualizzare stato e report SLA**; per esportarlo serve inoltre il
permesso **Esporta i report** – senza di esso i pulsanti di esportazione non
compaiono. Gli amministratori possono sempre fare entrambe le cose.
