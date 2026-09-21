---
title: "Inventario"
topic: inventory.counts
version: 1
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
