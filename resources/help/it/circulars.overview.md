---
title: "Circolari ai clienti"
topic: circulars.overview
version: 1
audience: []
related:
    - contacts.manage
    - invoices.manage
---

Le circolari sono comunicazioni commerciali a un gruppo filtrato di clienti
— adeguamento dei prezzi, finestra di manutenzione, orari di reperibilità
modificati. Non una newsletter: nessun pixel di tracciamento, nessun link
riscritto.

**Destinatari:** Il gruppo viene definito con i filtri clienti già esistenti
(ricerca, città, inizio del CAP, solo clienti con un progetto attivo). Prima
dell’invio compare il numero dei destinatari con l’elenco completo — una
mail a tutti i clienti non deve poter partire per sbaglio.

**Rifiuto degli invii collettivi:** I clienti con l’opzione *Nessun invio
collettivo* vengono saltati. Le circolari contrassegnate come *comunicazione
obbligatoria* li raggiungono comunque; ciò è riservato alle informazioni
previste dalla legge.

**Prova:** Ogni destinatario genera una riga — anche quelli saltati, con il
motivo (ad esempio un indirizzo e-mail mancante). La comunicazione viene
inoltre archiviata come nota nella scheda cliente e, se richiesto, compare
nel portale clienti.

**Segnaposto:** `:firma`, `:kunde` e `:ansprechpartner` vengono sostituiti
per ciascun destinatario. Se un valore manca, il posto resta vuoto — non si
inventa nulla.
