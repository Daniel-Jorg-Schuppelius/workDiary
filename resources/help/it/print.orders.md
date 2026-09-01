---
title: "Ordini di stampa (stampa & copisteria)"
topic: print.orders
version: 1
audience: []
modules:
    - module.lager
related:
    - claims.overview
    - documents.manage
---

Il profilo settoriale stampa/copisteria gestisce ogni ordine di stampa come
fascicolo specializzato collegato a un ordine di produzione: ricezione dati,
verifica file (preflight), visto si stampi, produzione, controllo qualità e
consegna formano un insieme riproducibile.

**File & preflight:** Il file di produzione risiede nell'archivio documenti
ed è legato all'ordine tramite checksum SHA-256. Il preflight distingue
errori (che bloccano il visto) da avvisi; una deroga manuale richiede una
motivazione ed è auditata. Una nuova versione del file riporta l'ordine
automaticamente a «verifica dati».

**Visto si stampi:** Il visto congela formato, supporto, quantità, colori,
scadenza e finitura insieme all'hash del file in uno snapshot di produzione
immutabile.

**Produzione & CQ:** Le macchine bloccate o con verifiche/tarature scadute
non possono partire regolarmente. Quantità buona e scarto confluiscono
tramite l'ordine di produzione in magazzino e consuntivazione. Il controllo
qualità confronta con lo stato approvato e documenta rilascio, blocco o
rilavorazione.

**Consegna & conservazione:** Il ritiro richiede una prova di consegna, la
spedizione usa la logistica esistente, la vendita al banco resta minimale
nei dati. Alla scadenza di conservazione viene rimosso solo il file del
cliente — ordine, snapshot e checksum restano come prova commerciale.
