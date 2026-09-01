---
title: "Rilevazione dei tempi basata sulla posizione"
topic: location.overview
version: 1
audience: []
modules:
    - module.standorterfassung
related:
    - time-entries.start
    - attendance.manage
---

La rilevazione dei tempi basata sulla posizione propone automaticamente
registrazioni orarie quando un dispositivo entra in una sede cliente
memorizzata e la lascia di nuovo. Integra la rilevazione manuale — non
si registra mai automaticamente, ma solo dopo conferma esplicita.

**Geofence per sede cliente:** per ogni sede cliente rilevante viene
definito un perimetro composto da punto centrale e raggio. Solo
all'interno di queste zone nascono soggiorni; i movimenti all'esterno
restano privi di rilevanza di merito.

**Fonti di dati:** le segnalazioni di posizione provengono a scelta
dalle app OwnTracks o Traccar tramite un accesso personale del
dispositivo, direttamente dal browser oppure a posteriori tramite
l'importazione di un file della cronologia delle posizioni di Google.
Ogni dispositivo viene registrato consapevolmente e la rilevazione
presuppone il consenso documentato della persona interessata.

**Dal segnale alla proposta:** i punti in ingresso vengono condensati
in soggiorni: l'ingresso e l'uscita da un geofence producono una visita
con inizio e fine. Le visite concluse compaiono come proposte in una
inbox di verifica personale — con cliente, eventualmente progetto e il
periodo rilevato.

**Verifica invece di automatismo:** solo la conferma di una proposta
genera una vera registrazione oraria; le proposte non pertinenti
possono essere scartate. Tra il segnale di posizione e la registrazione
c'è quindi sempre una decisione consapevole della persona interessata
stessa.

**Protezione dei dati:** vengono analizzati gli eventi di ingresso e di
uscita presso le sedi cliente memorizzate — non ha luogo alcuna
sorveglianza permanente della posizione. Ogni persona vede
esclusivamente la propria traccia di movimento e le proprie proposte;
nemmeno gli amministratori vi hanno accesso. I punti di posizione
grezzi vengono salvati in forma crittografata ed eliminati
automaticamente allo scadere di un periodo di conservazione (per
impostazione predefinita 90 giorni). Le registrazioni orarie confermate
e le analisi da esse derivate non ne sono toccate — scompare solo la
traccia grezza, non il tempo di lavoro.
