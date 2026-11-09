---
title: "Collegare DomainReselling"
topic: admin.domain-provider
version: 2
keywords:
    - gestione domini
    - nomi a dominio
    - rivenditore domini
    - DNS
    - record DNS
    - registrazione dominio
    - rinnovo dominio
    - registrar
    - portafoglio domini
audience:
    - admin
    - geschaeftsfuehrung
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
compaiono mai in URL, log o diagnostiche. Facoltativamente indichi un utente
predefinito (s_user): il contesto sotto cui vengono eseguiti i comandi di un
subutente autorizzato.

**Verificare e sincronizzare:** «Verifica connessione» controlla le
credenziali sull'API senza modificare nulla. «Sincronizza» importa il
portafoglio attuale (domini, scadenze, modalità di rinnovo,
rivenditori/subutenti) nelle proiezioni locali. La sincronizzazione è in
sola lettura e idempotente.

**Confermare il pilota:** Dopo un test reale riuscito conferma il pilota;
solo allora la connessione può passare in produzione. Finché il pilota
resta aperto, il controllo di stato segnala «pilota aperto».

**Rinnovare le credenziali:** L’icona della chiave apre la finestra
«Rinnova le credenziali». Inserisca una nuova password; login e utente
predefinito restano invariati se lascia i campi vuoti. WorkDiary verifica
subito le nuove credenziali — la connessione resta in essere con tutti i
domini, le assegnazioni e le evidenze.

**Disconnettere:** «Disconnetti» non elimina nulla. La password viene
rimossa, lo stato passa a *disconnessa* e nessuna sincronizzazione, lettura
degli eventi o comando raggiunge più il fornitore. Domini, contatti, zone
DNS, il registro dei comandi (chi ha modificato cosa presso il fornitore),
le registrazioni e i collegamenti di dominio dei Suoi abbonamenti di
rivendita e delle righe d’acquisto restano come evidenza. Per ricollegarsi
salvi nuove credenziali tramite «Rinnova le credenziali»; superata la
verifica, la connessione è di nuovo attiva.

**Stati:** Una connessione è in *bozza*, *attiva*, *bloccata* o
*disconnessa*. Le connessioni bloccate mostrano uno stato di blocco visibile
nel controllo di salute — mai un errore silenzioso. Il controllo di salute
non segnala una connessione disconnessa come guasto; è fuori servizio di
proposito.
