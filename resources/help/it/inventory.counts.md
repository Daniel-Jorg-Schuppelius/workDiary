---
title: "Inventario"
topic: inventory.counts
version: 4
keywords:
    - conta fisica
    - inventario fisico
    - inventario a rotazione
    - inventario annuale
    - conteggio magazzino
    - foglio di conteggio
    - contare la merce
    - differenze inventariali
    - registrare differenze
    - classe ABC
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
    - articles.master
---

In questa pagina esegue gli inventari per singolo magazzino: può aprire un
inventario completo o un conteggio ciclico parziale per classe ABC che
include solo le varianti in scadenza. Nella vista di dettaglio registra le
quantità contate per riga, anche tramite scansione; finché l'inventario è
aperto i risultati restano modificabili. La registrazione delle differenze
crea scritture di rettifica nello stock e chiude l'inventario: l'azione
richiede un permesso di approvazione dedicato e non è reversibile, verifichi
quindi i valori contati prima di applicare le differenze.

## Conteggio in unità e approvazione

Se un articolo ha altre unità, la quantità contata può essere inserita in una di esse per riga nell’elenco di conteggio, durante la scansione e nel conteggio mobile; viene salvata nell’unità base. Contare e approvare sono diritti distinti. Se l’approvazione a quattro occhi è attiva, le differenze a partire dalla soglia non vengono approvate da chi le ha contate.

**Contare offline con un’unità:** anche senza rete la vista mobile conta nell’unità scelta (ad esempio cartone). All’invio il server la converte nell’unità base, esattamente come online.

## Concludere, riaprire o annullare il conteggio

**Concludere il conteggio** blocca le quantità contate per la verifica; dopo non si può più registrare nulla. Chi approva le differenze può **riaprire il conteggio** per correggere, registrare le differenze o **annullare l'inventario**: un inventario annullato non registra nulla. Il passaggio di verifica è facoltativo; l'approvazione è possibile anche direttamente dal conteggio.
