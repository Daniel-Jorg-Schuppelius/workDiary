---
title: "Approvvigionamento e ordini"
topic: procurement.orders
version: 1
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Gli ordini registrano l'acquisto di articoli presso un fornitore verso un
magazzino di destinazione: si crea una bozza con righe d'ordine
(articolo, quantità, prezzo d'acquisto opzionale) e poi si ordina; lo
stato passa da bozza a ordinato, parzialmente consegnato, consegnato o
annullato. L'entrata merce si registra contro la singola riga d'ordine e
aumenta lo stock valorizzato; sono supportate consegne parziali ed
eccedenti, oltre agli avvisi di spedizione (ASN). Le proposte d'ordine
automatiche calcolano il fabbisogno per magazzino da scorta minima e
richieste aperte, considerando quantità minima e fornitore preferito.
Creare, ordinare e registrare richiede l'autorizzazione ai movimenti di
magazzino; l'annullamento di un ordine non è reversibile.
