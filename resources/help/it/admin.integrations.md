---
title: "Gestire le integrazioni"
topic: admin.integrations
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.lexoffice
---

Questa guida vale per tutte le pagine di gestione delle integrazioni –
ad esempio CalDAV, WebDAV, Todoist, Zammad, Kimai/Clockify, ricezione
e-mail, telefonia, messenger di team, terminali di timbratura,
spedizioni e SSO. Tutti i collegamenti seguono gli stessi principi di
base.

**Per organizzazione:** le integrazioni vengono attivate e configurate
per ciascuna organizzazione. Attivazione, credenziali, stato di salute
e cronologia degli errori valgono sempre solo per l'organizzazione
corrente – in un'altra organizzazione lo stesso collegamento può
trovarsi in uno stato completamente diverso.

**Credenziali:** token, password e identificativi dei dispositivi si
inseriscono nella configurazione del rispettivo plugin. I valori
sensibili vengono salvati in forma crittografata e dopo il salvataggio
non compaiono più in chiaro – né nell'interfaccia né nel registro di
audit.

**Healthcheck e disattivazione automatica:** ogni collegamento viene
monitorato costantemente per rilevare errori di connessione. Se gli
errori si accumulano oltre la soglia configurabile, il collegamento
viene disattivato automaticamente, in modo che non produca errori a
catena. Le integrazioni disattivate automaticamente restano visibili
nella panoramica e sono contrassegnate di conseguenza – una volta
rimossa la causa (ad es. rinnovando un token scaduto) possono essere
riattivate. Un singolo plugin difettoso non trascina mai con sé
l'applicazione: gli errori vengono registrati in modo isolato.

**Dati in ingresso – Inbox-First:** le importazioni non acquisiscono
nulla alla cieca. I record in ingresso arrivano prima nella Inbox
delle integrazioni, vengono confrontati con i dati esistenti e
acquisiti solo dopo una corrispondenza univoca o una decisione
manuale. I casi dubbi e i conflitti restano come voci aperte nella
Inbox finché non vengono risolti o scartati.

**Modifiche in uscita – Outbox:** le modifiche dirette al sistema
esterno passano attraverso una Outbox con ripetizione automatica. Se
una trasmissione fallisce, viene ritentata; i conflitti rilevati (ad
es. quando il sistema esterno è stato modificato nel frattempo)
tornano nella Inbox per il chiarimento. Così nessuna modifica va persa
e nulla viene scritto due volte.

**Raccomandazione:** dopo la configurazione di un nuovo collegamento,
verificare l'healthcheck, osservare per alcuni giorni la Inbox alla
ricerca di conflitti inattesi e solo allora impostare processi
automatizzati basati su di esso.
