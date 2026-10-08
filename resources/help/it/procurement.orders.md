---
title: "Approvvigionamento e ordini"
topic: procurement.orders
version: 3
keywords:
    - acquisti
    - ordine d'acquisto
    - ordine fornitore
    - entrata merci
    - consegna parziale
    - avviso di spedizione
    - proposte di riordino
    - punto di riordino
    - quantità minima d'ordine
    - consegne attese
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Gli ordini di acquisto registrano l’acquisto di articoli presso un
fornitore per un magazzino di destinazione. Li trova in **Vendite e
fatturazione** → **Approvvigionamento e cataloghi** → **Ordini di
acquisto**. Con **Nuovo ordine** nasce dapprima una bozza con
**Fornitore**, **Magazzino** e, facoltativamente, **Data di consegna** e
**Nota**. Con **Aggiungi riga** compila le righe d’ordine (**Articolo**,
**Quantità**, facoltativamente **Prezzo unitario**) e poi invia l’ordine
con **Ordina**. Si possono ordinare solo articoli con la caratteristica
**Acquistabile**. Lo stato passa per «Bozza», «Ordinato», «Parzialmente
ricevuto», «Ricevuto» o «Annullato».

L’**Entrata merci** si registra sulla singola riga d’ordine e aumenta lo
stock in modo valorizzato; sono supportate consegne parziali ed
eccedenti, e le colonne **Ordinato** e **Ricevuto** mostrano lo stato.
In alternativa, con **Inserisci avviso di spedizione** può registrare le
quantità annunciate per un ordine e in seguito acquisire l’entrata merci
con **Registra entrata merci**. La scheda **Entrate previste** apre la
vista «Entrate previste», che elenca le righe aperte degli ordini
inviati, ordinate per data di consegna.

La scheda **Proposte di ordine** determina, dopo **Seleziona
magazzino**, il **Fabbisogno** in base alla scorta di riordino e alle
richieste aperte e propone quantità (**Proposta**) tenendo conto della
quantità minima d’ordine e del fornitore preferito. **Crea ordini** ne
ricava bozze per fornitore, da verificare prima di ordinare. Creare,
ordinare e registrare richiedono il permesso **Registra movimenti di
magazzino**; **Annulla** su un ordine non è reversibile.
