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

**Terminali di timbratura, chiosco e punti di check-in:** Registrando un
terminale compaiono due indirizzi una sola volta: quello di ingest per i
terminali hardware e quello del chiosco, che trasforma il browser di un tablet
in un terminale. Entrambi contengono lo stesso token; se va perso, ruota il
token o disattiva il terminale. I badge letti dal chip NFC del tablet (Chrome su
Android) devono essere registrati come identificativo esadecimale senza
separatori. I punti di check-in sono codici QR o adesivi NFC presso sedi e
veicoli: la vista di stampa fornisce il codice e lo stesso indirizzo può essere
scritto su un adesivo con un'app NFC. Un codice può essere fotografato: per
dimostrare la presenza sul posto imposta un raggio. La posizione viene solo
verificata, non salvata.

I badge possono essere sostituiti da un **PIN del terminale**: l'amministrazione
lo imposta per persona con matricola; viene salvato solo un hash e dopo cinque
tentativi falliti è bloccato per 15 minuti e si può sbloccare qui.

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

## Quali integrazioni esistono

L'offerta cresce; l'elenco seguente indica le integrazioni disponibili per
scopo, così non devi indovinare dove va cosa:

- **Contabilità e fatturazione:** lexoffice, orgaMAX, sevDesk, easybill,
  BuchhaltungsButler, InvoicePlane e il punto di accesso Peppol per l'invio di
  fatture elettroniche.
- **Telefonia e messaggi:** sipgate e FRITZ!Box per chiamate in entrata e in
  uscita, seven.io per SMS a destinatari critici.
- **Spedizioni:** DHL, FedEx e UPS per etichette e tracciabilità.
- **File e backup:** Nextcloud, WebDAV, Dropbox, Google Drive, SharePoint e S3
  come destinazione di archiviazione o backup.
- **Calendario, contatti e posta:** Microsoft Graph, Google Calendar, CalDAV,
  CardDAV e Calendly per gli appuntamenti prenotati.
- **Progetti e tempi:** Todoist, OpenProject, GitHub, GitLab, Toggl, Clockify,
  Kimai, Zammad.
- **Commercio e gestionale:** JTL-Wawi, Billbee, Etsy.

Un'integrazione assente da questo elenco non esiste: nel dubbio chiedi, invece
di salvare credenziali in un punto non previsto.
