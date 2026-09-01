---
title: "Giacenze e scansione"
topic: inventory.stock
version: 1
audience: []
modules:
    - module.lager
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

La panoramica delle giacenze mostra per magazzino le quantità disponibili,
fisiche e riservate delle varianti, il prezzo medio mobile, il valore di
magazzino e la scorta minima di riordino. Con il permesso di registrazione
inserisci movimenti manuali (entrata, prelievo, riserva, rilascio) e imposti
scorte minime per variante e magazzino; i prelievi in negativo sono possibili
solo se li consenti espressamente. I lotti si gestiscono nell'apposito elenco
(divisione e unione), mentre la vista di scansione risolve un codice (numero
di serie, lotto, GTIN o SKU) e registra direttamente un'azione. Tutti i
movimenti finiscono nel giornale progressivo e non sono reversibili: le
correzioni avvengono tramite scritture di segno opposto.
