---
title: "Cambio di software contabile"
topic: admin.accounting-migration
version: 3
keywords:
    - migrazione contabilità
    - migrare dati contabili
    - cambio fornitore
    - Lexoffice
    - orgaMAX
    - sevDesk
    - easybill
    - migrazione dati
    - simulazione
    - dry run
    - data di passaggio
    - funzionamento parallelo
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

Il cambio di software contabile porta un'organizzazione da un sistema al
successivo in modo controllato. Origine e destinazione si scelgono
liberamente tra Lexoffice, orgaMAX, sevDesk ed easybill, in entrambe le
direzioni. WorkDiary non copia ciecamente da un sistema all'altro: associa
entrambi i sistemi esterni agli stessi oggetti di business locali.

Passi: pianificare (aree dati + data di switch, un solo cambio per
organizzazione alla volta) → analisi come simulazione (non scrive in alcun
sistema esterno) → decidere singolarmente i record ambigui → esercizio
parallelo (i vecchi documenti vengono chiusi nel sistema di origine) →
commutazione (dalla data di switch i nuovi documenti nascono esclusivamente
nel sistema di destinazione; il push verso l'origine è bloccato
tecnicamente e la commutazione resta bloccata finché esistono conflitti) →
conclusione con protocollo CSV.

Per sevDesk ed easybill:

- I **dati anagrafici** vengono riconciliati tramite il collegamento dei
  contatti: clienti e fornitori già collegati al programma (trasferimento,
  invio del contatto o sincronizzazione dei contatti) contano come
  assegnati.
- Gli **articoli** non si possono riconciliare con sevDesk ed easybill,
  perché WorkDiary rispecchia soltanto la loro anagrafica articoli senza
  collegarla agli articoli locali. In tal caso deselezioni l’area. Il modulo
  la contrassegna («non con sevDesk, easybill»); se la pianificazione non
  riesce per questo motivo, origine, destinazione e data di switch restano
  impostate e l’area è deselezionata.
- Lo **storico dei documenti** proviene dallo specchio dei documenti.
  Esegua il recupero dei documenti prima dell’analisi e prima della
  conclusione, così che i vecchi documenti aperti siano completi.
- Le immagini dei documenti restano nel programma. Le salvi tramite la sua
  esportazione prima della fine del contratto.

Principi: i documenti finalizzati **non** vengono mai ricostruiti nel
sistema di destinazione — restano consultabili come storico con numero,
stato e origine. Ogni passo è registrato in una catena di eventi a prova di
manomissione.
