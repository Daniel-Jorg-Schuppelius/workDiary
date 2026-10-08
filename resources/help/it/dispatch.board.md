---
title: "Centrale operativa: board e mappa"
topic: dispatch.board
version: 3
keywords:
    - tabellone di pianificazione
    - pianificazione interventi
    - kanban
    - vista mappa
    - mappa degli interventi
    - vista per tecnico
    - rischio SLA
    - dispatcher
    - pianificatore
    - sala operativa
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
    - tours.manage
    - sla.overview
---

La **Centrale operativa** mostra a colpo d'occhio gli ordini aperti e
pianificati di un periodo — come **Bacheca** (colonne) oppure come
**Mappa**. È una semplice panoramica: tutte le modifiche continuano a essere
eseguite nel rispettivo ordine.

## Bacheca

La bacheca raggruppa gli ordini del periodo selezionato, a scelta:

- **Per stato**: colonne in base allo stato di pianificazione (Non
  pianificato, Pianificato, Confermato, In viaggio, Completato).
- **Per dipendente**: una corsia per ogni dipendente assegnato.

Ogni scheda indica cliente, fascia oraria e dipendente e segnala
situazioni particolari:

- **Conflitto**: per l'assegnazione attuale esiste un **conflitto di
  pianificazione bloccante** (ad es. doppia pianificazione,
  sovrapposizione di turni).
- **SLA**: per il cliente un ticket di assistenza è **a rischio** oppure
  **violato** (rischio SLA).

Un clic su una scheda apre l'ordine.

## Mappa

La mappa colloca gli ordini in base alla loro ubicazione oppure — se
questa non è indicata — in base all'**ubicazione del cliente**. Il colore
del marcatore segue lo stato di pianificazione; gli ordini con **SLA a
rischio o violato** vengono evidenziati in **rosso**. Tramite i filtri si
possono visualizzare in modo mirato **solo i rischi SLA** oppure **solo
gli ordini non confermati**.

## Volutamente non incluso

La centrale operativa è pura visualizzazione. **Ottimizzazione dei
percorsi**, **tracciamento in tempo reale** e **monitoraggio permanente
della posizione** non fanno parte di questa vista per motivi di
protezione dei dati.
