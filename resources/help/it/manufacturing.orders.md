---
title: "Ordini di produzione"
topic: manufacturing.orders
version: 1
keywords:
    - ordine di lavorazione
    - distinta base
    - ricetta
    - fabbisogno materiali
    - MRP
    - dichiarazione di produzione
    - scarti
    - conto lavoro
    - documenti doganali
    - fattura proforma
    - fattura commerciale
    - documento di trasporto
    - DDT
audience: []
modules:
    - module.lager
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
essere impostati). Dalla pagina di dettaglio assegna l'ordine a un
centro di lavoro o lo affida in conto lavoro a un fornitore; la vista di
pianificazione mostra l'MRP multilivello e gli indicatori di qualità.
L'annullamento è irreversibile; creare, confermare e consegnare
richiedono l'autorizzazione alle registrazioni di magazzino.

## Documenti doganali per spedizioni fuori dall’UE

Per ogni consegna con destinatario, «Documenti doganali» crea una fattura
commerciale (in caso di vendita) o una fattura proforma (regalo, campione,
merce resa, riparazione e altri motivi) in PDF. La finestra indica se la
destinazione è fuori dall’UE e salva il motivo scelto sulla consegna. Il
documento riporta descrizione della merce, codice doganale, paese d’origine,
quantità, peso netto e valore; peso lordo e numero di colli derivano dai colli
registrati. Gestisca codice doganale, paese d’origine e peso netto
sull’articolo e il numero EORI del mittente nelle impostazioni
dell’organizzazione. Se manca un dato, la finestra lo indica e non crea alcun
documento. I documenti doganali non sostituiscono una dichiarazione di
esportazione elettronica.

La pagina **Documenti di trasporto** elenca tutte le consegne del periodo scelto
— con PDF del documento, invio per e-mail, documenti doganali e stato della
spedizione, senza passare da ogni ordine di produzione. Il filtro «Non spedite»
mostra ciò che attende ancora un'etichetta.
