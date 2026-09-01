---
title: "Collegare DomainReselling"
topic: admin.domain-provider
version: 1
audience:
    - admin
modules:
    - module.domain
related:
    - admin.plugins
    - admin.integrations
---

WorkDiary collega un **account DomainReselling** per organizzazione e ne
gestisce i domini in modo controllato: leggere il portafoglio, assegnare
clienti, curare scadenze e DNS e sottoporre le azioni ad alto rischio ad
approvazione. Questa pagina configura la connessione; il lavoro sui domini
avviene poi nel modulo «Domini».

**Scegliere l'ambiente:** Ogni connessione funziona in *OT&E* (l'ambiente di
test/pilota) oppure in *produzione*. I nuovi account iniziano in OT&E; il
passaggio in produzione si sblocca solo dopo un pilota superato e confermato
in modo reale, così nessuna registrazione reale finisce per errore in un
test.

**Credenziali:** Login e password vengono memorizzati cifrati e non
compaiono mai in URL, log o diagnostiche. Facoltativamente indica un utente
predefinito (s_user): il contesto sotto cui vengono eseguiti i comandi di un
subutente autorizzato.

**Verificare e sincronizzare:** «Verifica connessione» controlla le
credenziali sull'API senza modificare nulla. «Sincronizza» importa il
portafoglio attuale (domini, scadenze, modalità di rinnovo,
rivenditori/subutenti) nelle proiezioni locali. La sincronizzazione è in
sola lettura e idempotente.

**Confermare il pilota:** Dopo un test reale riuscito confermi il pilota;
solo allora la connessione può passare in produzione. Finché il pilota
resta aperto, il controllo di stato segnala «pilota aperto».

**Ruotare le credenziali e disconnettere:** Login/password possono essere
reimpostati in qualsiasi momento (rotazione) senza ricreare la connessione.
La disconnessione rimuove la connessione; i dati di proiezione già letti
vengono conservati come prova.

**Stati:** Una connessione è in *bozza*, *attiva* o *bloccata*. Le
connessioni bloccate mostrano uno stato bloccato visibile nel controllo di
stato, mai un errore silenzioso.
