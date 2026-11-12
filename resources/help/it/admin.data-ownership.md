---
title: "Titolarità dei dati"
topic: admin.data-ownership
version: 3
keywords:
    - sistema master
    - sistema di riferimento
    - fonte unica di verità
    - sovranità dei dati
    - titolarità fatturazione
    - Lexoffice
    - DATEV
    - evitare doppia fatturazione
    - conflitto di sincronizzazione
    - conflitto di titolarità
    - acquisisci una volta
audience:
    - admin
related:
    - admin.integration-inbox
    - admin.tenants
    - finance.transfers
---

Questa pagina stabilisce, per ciascuna organizzazione, quale sistema è
**titolare** di quale ambito di dati – affinché due sistemi non possano
mai sovrascrivere reciprocamente gli stessi dati.

**La matrice:** Per ogni area di dati – attività, ticket, giacenze, calendario, documenti, clienti e fornitori – vale esattamente **un sistema principale**: WorkDiary stesso (l’impostazione predefinita) o un’integrazione attivata. Per ogni area sono proposte solo le integrazioni che possono davvero scriverla. Le **giacenze** non si cambiano qui, ma nel collegamento del gestionale di magazzino (ad es. JTL-Wawi → gestione delle giacenze); la matrice mostra quell’impostazione. La doppia gestione è esclusa strutturalmente.

**Effetto della gestione:** Se gestisce WorkDiary, le importazioni dalle integrazioni restano consentite come sempre. Se un’integrazione gestisce un’area, solo lei può scrivervi. Se un’altra integrazione vuole scrivere – creare o chiudere un ticket da Zammad, acquisire una prenotazione Calendly o un appuntamento del calendario, registrare giacenze iniziali da JTL-Wawi, importare un’attività – non scrive nulla. Al suo posto nella **casella delle integrazioni** compare un caso «Titolarità dei dati»: **Acquisisci una volta** scrive lo stato esattamente una volta, **Scarta** lo lascia cadere e la stessa operazione non ritorna. Leggere e visualizzare restano sempre consentiti.

**Cambio di gestione:** Ogni modifica viene registrata nell’audit. Al cambio i conflitti aperti restano nella casella; la matrice ne indica il numero per area. Se la nuova gestione consente la via di scrittura, la sincronizzazione successiva chiude il caso da sola; altrimenti lo acquisisca o lo scarti lì.

**Code di ticket gestite esternamente:** Se Zammad importa ticket di servizio in una coda, Zammad gestisce quella coda. Lì nascono nuovi ticket solo tramite l’importazione; WorkDiary non accetta in quella coda creazione manuale, registrazione di e-mail né risposte via e-mail. Stato e comunicazione con il cliente si gestiscono nel sistema principale.

**Documenti:** se un’integrazione gestisce i documenti, solo essa crea documenti e nuove versioni nel DMS tramite l’acquisizione documenti cloud. I file di altri collegamenti cloud e i documenti recuperati da altri programmi (ad esempio da easybill) attendono come caso «Titolarità dei dati»; **Acquisisci una volta** scarica di nuovo il file e lo crea. I propri caricamenti, protocolli e documenti restano possibili in WorkDiary: il DMS è anche l’archivio delle prove di WorkDiary.

**Clienti e fornitori:** questa riga è anche la titolarità dei dati anagrafici nei confronti della contabilità. Se sceglie un programma di contabilità (Lexoffice, sevDesk, easybill), questo gestisce i dati anagrafici: WorkDiary non vi trasferisce contatti e la sincronizzazione dei contatti del programma acquisisce nomi, dati fiscali e indirizzi. Gli altri programmi non modificano più clienti e fornitori e la migrazione Toggl non ne crea; i loro stati attendono nella posta in arrivo delle integrazioni. «Contabilità (qualsiasi programma che scrive clienti)» lascia scrivere ogni programma collegato. Le modifiche locali restano possibili, ma non vengono ritrasmesse e la sincronizzazione successiva le sovrascrive. I numeri cliente continuano a essere assegnati dalla serie di numerazione, non da questa riga.

**Casi acquisiti:** se in seguito l’integrazione vuole scrivere un nuovo stato della stessa operazione, ad esempio un’attività modificata o una nuova revisione di file, il caso acquisito si riapre. I casi scartati restano scartati.

**Sovranità di fatturazione:** per la fatturazione vale lo stesso
principio: esattamente un programma è titolare delle fatture –
WorkDiary, Lexoffice o DATEV. Il canale di fatturazione si imposta
come **predefinito per organizzazione** e può essere sovrascritto
**per singolo cliente**. Vale la cascata: l'impostazione del cliente
prevale sul predefinito dell'organizzazione; in assenza di entrambi,
WorkDiary fattura localmente.

**Conseguenze della titolarità esterna:** se un programma esterno è
titolare della fatturazione di un cliente, la **creazione locale di
fatture per quel cliente è bloccata**. I tempi e i materiali
fatturabili vengono invece trasmessi come **attestato di consegna** al
programma titolare: le consegne nascono dapprima come bozza, vengono
confermate e solo con la consegna effettiva le voci di origine vengono
consumate come fatturate – in questo modo nulla può essere fatturato
due volte. L'assegnazione vincolante dei numeri di fattura resta
interamente al programma titolare.

**Cambio in esercizio:** un cambio del canale di fatturazione ha
effetto solo sulle operazioni future; i documenti già emessi restano
invariati. Prima del passaggio è opportuno chiarire quali partite
aperte debbano ancora essere chiuse attraverso il canale precedente.

**Raccomandazione:** mantenere la matrice volutamente snella –
trasferire la titolarità a un'integrazione solo dove il sistema
esterno è effettivamente la fonte di dati determinante.
