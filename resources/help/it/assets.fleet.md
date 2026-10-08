---
title: "Asset e parco veicoli"
topic: assets.fleet
version: 2
keywords:
    - gestione flotta
    - gestione veicoli
    - inventario
    - attrezzature
    - consegna attrezzatura
    - prestito attrezzi
    - restituzione
    - registro carburante
    - registro ricariche
    - manutenzione programmata
    - segnalare guasto
    - ciclo di vita
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Asset e veicoli rappresentano oggetti aziendali con stato,
responsabilità, documenti e informazioni di manutenzione. I registri
di rifornimento e di ricarica completano lo storico dei consumi.

Registri i dati anagrafici e gli identificativi univoci, assegni la
sede o i responsabili e aggiorni gli intervalli di manutenzione e i
documenti rilevanti. Le modifiche di stato dovrebbero rispecchiare il
ciclo di vita effettivo.

Prima dell'eliminazione o della dismissione, verifichi se sono
collegati manutenzioni, pratiche, viaggi o documenti aperti. Lo storico
critico dovrebbe essere archiviato e non andare perso a causa di
sovrascritture.

## Assegnazione e restituzione

Tramite il pannello «Assegnazione / restituzione» nella pagina di
dettaglio dell'asset, un dispositivo viene assegnato a una persona o a
un team, facoltativamente con riferimento a un ordine e restituzione
prevista. Per ogni asset esiste al massimo un'assegnazione aperta; un
asset già assegnato o bloccato per un difetto non può essere assegnato
di nuovo. Con la restituzione l'asset torna disponibile. Se
un'assegnazione supera la restituzione prevista, compare un avviso di
ritardo e lo scanner delle scadenze avvisa la persona che ha preso in
prestito l'asset oppure il responsabile del team.

## Difetti e blocchi

Nel pannello «Difetti / blocchi» si possono registrare difetti con il
relativo livello di gravità. Se è impostato «Blocca asset (nessun
prelievo possibile)», il difetto aperto blocca qualsiasi ulteriore
assegnazione finché non viene risolto o stornato. Per risolverlo o
stornarlo è necessaria una nota di risoluzione.

## Fascicolo oggetto (ciclo di vita)

Il «Fascicolo oggetto» raccoglie l'intero ciclo di vita di un asset in
una vista coerente e stampabile: dati anagrafici, sede e locale, stato
del ciclo di vita derivato (in esercizio, sostituito o dismesso),
messa in servizio, messa fuori servizio e garanzia. Più sotto compaiono
manutenzioni, assegnazioni e restituzioni, difetti e blocchi, ordini
collegati, verbali, impieghi di materiale, punti aperti e allegati,
nonché lo storico completo del ciclo di vita.

Il fascicolo è raggiungibile tramite il pulsante «Fascicolo
dell'oggetto» nella pagina di dettaglio dell'asset e può essere
emesso come documento tramite la funzione di stampa del browser
(aggiungendo «?print=1» si apre direttamente la finestra di stampa).
Lo stato del ciclo di vita viene derivato da stato, messa fuori
servizio e garanzia – non è prevista una gestione separata.
