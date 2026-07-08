---
title: "Ordini di produzione"
topic: manufacturing.orders
version: 1
audience: []
related:
    - manufacturing.work-centers
    - procurement.orders
    - articles.master
    - inventory.stock
---

Gli ordini di produzione rappresentano la fabbricazione di un prodotto in
base alla sua distinta base o ricetta; sono selezionabili solo articoli
marcati come producibili e il fabbisogno di materiale è derivato da
quantità, variante e distinta. Con il rilascio viene salvato uno snapshot
della distinta, così le modifiche successive non toccano l'ordine in
corso. Il flusso segue una macchina a stati (bozza, rilasciato, in
lavorazione, in attesa, bloccato, completato, annullato): il materiale si
blocca con "Riservare", le conferme parziali registrano quantità
prodotte, buone, di scarto e di rilavorazione, e i prodotti finiti si
caricano a magazzino con "Consegnare" (variante e magazzino devono
essere impostati). Dalla pagina di dettaglio assegni l'ordine a un
centro di lavoro o lo affidi in conto lavoro a un fornitore; la vista di
pianificazione mostra l'MRP multilivello e gli indicatori di qualità.
L'annullamento è irreversibile; creare, confermare e consegnare
richiedono l'autorizzazione alle registrazioni di magazzino.
