---
title: "Impianti sportivi e risorse"
topic: club.resources
version: 1
audience: []
modules:
    - module.club
related:
    - club.events
    - club.matches
---

Impianti e risorse rappresentano palestre, aree parziali (metà, terzo), tavoli,
campi, corsie e postazioni, barche e attrezzi come **albero**: un'area parziale
dipende dalla sua palestra, un tavolo dalla palestra o da una metà. Il
controllo dei conflitti è comune — prenotare l'intera palestra blocca tutte le
aree e i tavoli sottostanti, aree libere diverse sono utilizzabili in
parallelo. Non esistono calendari isolati per sport.

**Sale e asset:** Una risorsa può essere collegata a una sala esistente; allora
gli appuntamenti con quella sala e le prenotazioni della risorsa (aree
comprese) condividono lo stesso calendario. Barche e attrezzi possono essere
collegati a un asset: i blocchi dell'asset (manutenzione, difetto, verifica)
impediscono la prenotazione — nessuna seconda disponibilità per lo stesso
oggetto e nessun obbligo di creare un contratto di noleggio.

**Unità e margini:** Una risorsa con più unità (es. quattro corsie) può essere
prenotata parzialmente; la quantità per appuntamento viene verificata contro
le unità. I margini di allestimento e smontaggio ampliano la finestra
prenotata.

**Prenotare:** Le risorse si prenotano sull'appuntamento o sulla giornata —
con quantità, finestra propria facoltativa, margini e persona utilizzatrice.
La prenotazione verifica capacità, palestra/aree, calendario sale, chiusure e
blocchi asset in una transazione; due prenotazioni simultanee non vengono mai
confermate entrambe. I luoghi delle trasferte sono indicazioni e non prenotano
nulla.

**Spostare e annullare:** Se un appuntamento viene spostato, le sue
prenotazioni lo seguono — in caso di conflitto tutto resta com'era (vecchio
orario, vecchia prenotazione). Un annullamento libera tutte le prenotazioni.

**Chiusure:** Meteo, manutenzione o uso esterno vengono registrati come
chiusura con motivo. Le prenotazioni esistenti vengono **segnalate** per la
ripianificazione, non cancellate; le nuove prenotazioni nel periodo sono
bloccate. Rimuovere la chiusura libera le prenotazioni segnalate.

**Abilitazioni:** Barche, attrezzi e risorse simili possono richiedere
un'abilitazione di istruzione o idoneità per socio, a scadenza facoltativa e
concessa dalla dirigenza. Senza abilitazione valida la persona non può essere
indicata come utilizzatrice; sull'appuntamento vengono mostrati i partecipanti
iscritti senza abilitazione.
