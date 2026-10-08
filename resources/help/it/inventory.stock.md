---
title: "Giacenze e scansione"
topic: inventory.stock
version: 2
keywords:
    - magazzino
    - carico merce
    - scarico magazzino
    - trasferimento di magazzino
    - prenotazione
    - punto di riordino
    - scorta minima
    - bloccare lotto
    - gestione lotti
    - FEFO
    - scansionare codice a barre
    - valore di magazzino
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
inserisce movimenti manuali (entrata, prelievo, riserva, rilascio) e imposta
scorte minime per variante e magazzino; i prelievi in negativo sono possibili
solo se li consente espressamente. I lotti si gestiscono nell'apposito elenco
(divisione e unione), mentre la vista di scansione risolve un codice (numero
di serie, lotto, GTIN o SKU) e registra direttamente un'azione. Tutti i
movimenti finiscono nel giornale progressivo e non sono reversibili: le
correzioni avvengono tramite scritture di segno opposto.

Un lotto può essere **bloccato** e di nuovo **sbloccato** nell’elenco dei
lotti — sempre con una motivazione; entrambe le azioni restano nel registro
di audit. La giacenza di un lotto bloccato resta in magazzino, ma la lista di
prelievo non lo propone più; divisione e unione sono possibili solo dopo lo
sblocco. Con l’unione, la giacenza del lotto di origine passa al lotto di
destinazione tramite scritture di segno opposto (trasferimento
uscita/entrata); il lotto unito è poi chiuso e non accetta più entrate.

**Prelievo per lotto.** Se il magazzino contiene giacenze a lotti, il
modulo di registrazione offre il campo «Lotto (prelievo)»: se lo lascia
vuoto, il prelievo segue FEFO (prima la scadenza più vicina, come nella
lista di prelievo); altrimenti viene prelevato esattamente il lotto scelto.
Per entrata, riserva e rilascio il campo non ha effetto. Gli articoli a
lotti si possono prelevare così finché i loro lotti coprono la quantità —
per essi non si preleva giacenza senza lotto; l’entrata passa sempre dal
ricevimento merci. Un trasferimento tramite scansione porta i lotti nel
magazzino di destinazione; un codice lotto scansionato registra esattamente
quel lotto.

**Blocco nella giacenza.** Bloccare un lotto registra la sua giacenza nello
stato «bloccato»: resta in magazzino, ma non conta più come disponibile e
compare nella panoramica delle giacenze nella colonna «Bloccato». Un lotto
bloccato non accetta ricevimenti; resi e ricollocazioni in esso restano
bloccati. Lo sblocco storna la giacenza bloccata. Anche la divisione di un
lotto è una registrazione: la quantità divisa passa al nuovo lotto nel
giornale dei movimenti.

**Ripulire la giacenza storica.** Fino a ottobre 2026 i prelievi non
portavano il lotto; la giacenza di un lotto può quindi superare quanto ne
resta davvero. Il Suo amministratore lo verifica con il comando
`inventory:lots:repair`: senza opzioni è una simulazione con una tabella per
lotto (saldo contabile, residuo degli strati di valutazione, differenza);
con `--apply` trasferisce la differenza a «senza lotto» con valutazione FIFO
o FEFO, e la giacenza totale resta invariata. Con il prezzo medio mobile
segnala soltanto il lotto; i lotti bloccati solo dopo lo sblocco.
