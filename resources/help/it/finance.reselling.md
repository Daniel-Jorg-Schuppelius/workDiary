---
title: "Riconciliazione licenze"
topic: finance.reselling
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

La **riconciliazione della rivendita licenze** verifica che ogni periodo di
fatturazione degli abbonamenti Microsoft 365 rivenduti sia coperto da una
fattura emessa in Lexoffice e confronta i prezzi di vendita con quelli di
acquisto.

**Cosa carichi:** l’export del Telekom Cloud Marketplace (purchases.csv),
l’export dei contratti del portale partner Quality Hosting (XLSX) e, in via
facoltativa, il suo listino prezzi. I due export insieme formano il parco
prima e dopo la migrazione; le successioni vengono riconosciute e la durata
Telekom viene troncata all’inizio del contratto Quality Hosting.

**Cosa fa l’esecuzione:** suddivide ogni abbonamento in periodi annuali o
mensili, assegna ogni azienda marketplace a un contatto Lexoffice (file di
assegnazione, numero cliente partner, anagrafica clienti, ricerca univoca per
nome — mai per ipotesi) e cerca per ogni periodo una riga di fattura
corrispondente nella finestra intorno all’inizio del periodo.

**Stato per periodo:** Coperto, Sotto costo, Parziale, Solo importo
(documento senza posizioni), Mancante, Senza assegnazione. Le
aziende senza assegnazione si risolvono alla prossima esecuzione con un file
di assegnazione: una riga per azienda, `Azienda;UUID contatto Lexoffice` o
`Azienda;customer:<Sqid>`.

**Controllo prezzi:** per prodotto vedi il prezzo d’acquisto dei contratti,
il prezzo di listino attuale e il prezzo consigliato dal produttore, oltre ai
prezzi di vendita unitari effettivamente fatturati. Compare un avviso se il
tuo prezzo è sotto costo o sotto il prezzo consigliato, o se un contratto
attivo costa più del listino attuale.

L’esecuzione legge Lexoffice in background e richiede alcuni minuti con molti
clienti. Non scrive nulla in Lexoffice né nell’anagrafica — il report vive
solo sull’esecuzione e si scarica come CSV.
