---
title: "Piano dei servizi per cliente e periodo"
topic: service-plan.overview
version: 1
keywords:
    - piano dei servizi
    - elenco annuale
    - elenco mensile
    - ambito di servizi
    - non dovuto
    - documenti ricevuti
    - i miei periodi
audience: []
related:
    - customers.deadlines
    - diary-entries.follow-up
    - diary-entries.review
    - service-plan.import
---

Il **piano dei servizi** mostra quali servizi ricorrenti ricevono i suoi
clienti e a che punto sono, senza tenere un secondo elenco. Lo stato deriva
dagli ordini con **periodo**; i servizi di un cliente sono indicati nel suo
**ambito di servizi**.

## Due viste

- **Vista annuale:** le righe sono clienti con un servizio annuale, le
  colonne i servizi (ad es. bilancio annuale, imposta sul reddito). Scelga le
  colonne con **Servizi** nella barra dei filtri; in **Filtri salvati**
  conserva la selezione come vista. «Anno precedente» e «anno in corso» si
  aggiornano nelle viste salvate.
- **Vista per periodi:** le righe sono cliente × servizio per mese, trimestre
  o semestre, le colonne i dodici mesi. Un trimestre copre tre mesi, un
  semestre sei. Davanti compaiono le caratteristiche del set di regole delle
  scadenze clienti (ad es. periodicità IVA, grado di non autosufficienza).

## Cosa mostra una cella

- Se esiste un ordine del periodo, la cella ne mostra lo stato: creato,
  accettato, in lavorazione, in attesa, in verifica, completato.
- Senza ordine un **servizio obbligatorio** mostra «mancante»; un **servizio
  facoltativo** non viene segnalato.
- Segni **non dovuto** con un motivo finché non esiste un ordine.
- La **scadenza** deriva dall’ordine, altrimenti dalla scadenza del cliente
  corrispondente; nel corso dell’anno, in ultima istanza, dalla fine del
  periodo. Ciò che la supera è segnato in rosso.
- Ogni cella conserva **documenti ricevuti il** e una **nota**; dove il set di
  regole lo conosce, compare anche **richiesto in anticipo il**.

Un clic sulla cella la apre: **Crea ordine** precompila la finestra
dell’ordine con cliente, tipo di voce, gruppo di prodotti, periodo,
responsabile e scadenza; se l’ordine esiste, lo apra da lì.

## Contatori e filtri

I contatori contano celle (mancante, aperto, in verifica, completato, non
dovuto, scaduto) e filtrano i clienti con tali celle. Altri filtri: persona
responsabile, gruppo di clienti, ricerca per nome o numero cliente.

## Creare i periodi dell’anno

Nella vista per periodi, **Crea i periodi dell’anno** crea gli ordini di tutti
i periodi di un servizio, ciascuno come seguito del precedente, con
responsabile, verifica obbligatoria, procedura e promemoria. Gli ordini
esistenti e «non dovuto» restano; i clienti senza predecessore ricevono il
primo ordine tramite la cella.

## Responsabilità e sostituzione

Responsabile è la persona dell’ordine; senza ordine, la persona responsabile
delle caratteristiche delle scadenze. **Miei** mostra anche le celle delle
persone che oggi sostituisce. Senza il permesso «Visualizzare il piano dei
servizi» vede solo queste celle. La scheda **I miei periodi** nella dashboard
e la coda di lavoro («Periodi scaduti») mostrano ciò che è aperto.

## Modelli di servizi

I profili di settore forniscono **modelli di servizi** (ad es. dichiarazioni e
bilancio annuale, assistenza mensile, controllo qualità, visita di
consulenza). Quando crea un cliente, la finestra li propone come ambito di
servizi.
