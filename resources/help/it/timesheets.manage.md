---
title: "Gestire i fogli ore"
topic: timesheets.manage
version: 5
keywords:
    - rapportino
    - rapportino di lavoro
    - resoconto ore
    - prova delle prestazioni
    - bolla di lavoro
    - far firmare
    - firma del cliente
    - registrare materiale
    - fatturare prestazioni
    - foglio ore PDF
audience: []
related:
    - time-entries.start
    - protocols.sign
    - finance.transfers
---

I fogli ore raggruppano le prestazioni di un progetto o di un periodo per
verifica, firma e successiva fatturazione; le posizioni possono contenere
tempi, attività e materiale. Flusso tipico: scelga il progetto, inserisca le
posizioni, controlli i totali, metta il foglio ore a disposizione per la
firma e lo chiuda dopo la conferma. I contenuti già firmati non vanno mai
modificati in silenzio: le correzioni avvengono in modo tracciabile con una
nuova versione. Prima dell'approvazione devono essere corretti periodo,
cliente, progetto, descrizione delle prestazioni e quantità; il PDF è solo
un'uscita dello stato salvato, non la fonte dati primaria.

**Materiale da «Oggi»:** con «Materiale» su «Oggi» registra materiale di consumo con progetto, quantità e unità, anche senza rete. Finisce sul Suo foglio ore aperto del progetto per quel giorno; se non esiste (o è già firmato), ne viene creato uno nuovo.

**Prelievo di magazzino:** se il materiale è collegato a un articolo gestito a magazzino (articolo contrassegnato «Stoccabile») e il modulo di magazzino è attivo, il consumo registra il prelievo dal magazzino predefinito dell’organizzazione, nell’unità inserita e valorizzato secondo il metodo di valorizzazione del magazzino. Se modifica la quantità, viene registrata solo la differenza; un altro materiale riporta in giacenza l’articolo precedente, e l’eliminazione della riga o dell’intero foglio ore annulla il prelievo. Sotto la descrizione la riga mostra se e da quale magazzino è stato registrato. Se la giacenza non basta, la registrazione viene rifiutata; con il permesso «Autorizzare giacenze negative» selezioni «Consenti giacenza negativa». Se l’organizzazione richiede per questo quattro occhi, il prelievo attende l’approvazione nella casella delle approvazioni («Giacenza negativa richiesta»). Gli articoli con numero di serie si prelevano tramite il magazzino; quelli a lotto vengono prelevati dai lotti, prima la scadenza più vicina. Senza magazzino predefinito la riga mostra «Non registrato – nessun magazzino predefinito»; se la giacenza è gestita in un altro sistema in sola lettura, WorkDiary non registra nulla. Se un altro sistema gestisce la giacenza, ogni movimento viene inviato anche lì. Lo stesso vale offline: se la giacenza impedisce la registrazione, la voce compare come conflitto in «Modifiche offline».

**Variante:** se l’articolo gestito a magazzino del materiale ha più varianti attive (ad esempio colori o taglie), scelga la variante consumata nella finestra del foglio ore e su «Oggi»; il campo compare solo per questi materiali. Senza scelta, il consumo registra come prima la variante predefinita. Se lascia vuoto il prezzo unitario, vale il prezzo di vendita proprio della variante, altrimenti quello del materiale. Un cambio di variante riporta in giacenza la precedente e preleva la nuova. Riga, PDF, fattura e trasferimento al programma contabile indicano la variante dopo la descrizione, come per una consegna.

**Offline con giacenza insufficiente:** se per il materiale registrato offline manca solo la giacenza, «Modifica» in «Modifiche offline» offre, con il permesso «Autorizzare giacenze negative», la casella «Consenti giacenza negativa». L’invio funziona come online: se sono richiesti quattro occhi, viene creata una richiesta nella casella delle approvazioni.

**Limite di somma:** il materiale registrato su «Oggi» finisce sul foglio ore aperto del giorno. Se quantità × prezzo unitario, insieme alle altre righe, superasse la somma memorizzabile del foglio, la registrazione viene respinta, online come offline.
