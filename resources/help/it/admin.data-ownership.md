---
title: "Titolarità dei dati"
topic: admin.data-ownership
version: 1
audience:
    - admin
related:
    - admin.tenants
    - finance.transfers
---

Questa pagina stabilisce, per ciascuna organizzazione, quale sistema è
**titolare** di quale ambito di dati – affinché due sistemi non possano
mai sovrascrivere reciprocamente gli stessi dati.

**La matrice:** per ogni ambito di dati (ad es. attività, ticket,
giacenze, calendari, documenti, clienti) vale esattamente **un sistema
titolare**: WorkDiary stesso («nativo», l'impostazione predefinita)
oppure un'integrazione attivata. La doppia titolarità è esclusa a
livello strutturale.

**Effetto della titolarità:** se la titolarità resta a WorkDiary, le
importazioni dalle integrazioni restano consentite come di consueto
tramite la Inbox. Se un'integrazione è titolare di un ambito, soltanto
essa può scrivervi – i tentativi di scrittura di altre integrazioni
finiscono come conflitto nella Inbox invece di modificare i dati. Ogni
modifica della titolarità viene registrata nell'audit trail.

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
