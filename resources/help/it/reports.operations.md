---
title: "Operatività: ripartizione del tempo, procedure, materiale, reperibilità"
topic: reports.operations
version: 6
keywords:
    - report operativo
    - ordini di servizio
    - analisi centri di costo
    - deviazioni di procedura
    - procedure bloccate
    - consumo di materiale
    - reperibilità
    - tasso di difetti
    - ore di progetto
    - archiviare progetti
    - classificazione mancante
    - analisi dei giri
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - reports.overview
    - reports.drilldown
    - procedures.run
    - materials.manage
    - duties.overview
    - projects.manage
    - admin.time-dimensions
    - admin.classifications
---

Questi report mostrano che cosa succede nell’operatività quotidiana: ordini
di servizio, attività e giri, la ripartizione del tempo di lavoro su
progetti, centri di costo e altre dimensioni, deviazioni e blocchi nelle
procedure, il materiale consumato, i servizi di reperibilità, i difetti sui
prodotti nonché le ore e le fasi di inattività dei singoli progetti. Trova la
maggior parte delle pagine in **Report** → **Progetti e clienti** e
**Report** → **Risorse**.

## Periodo, filtri ed esportazione

- Il periodo si sceglie con la selezione del periodo nell’intestazione. La
  barra dei filtri lo mostra solo come indicazione; le eccezioni sono
  descritte nel rispettivo report.
- I filtri si applicano subito dopo la selezione. L’interruttore **Includi i
  clienti nascosti** compare solo se ci sono clienti contrassegnati con
  **Nascondi nelle analisi**; senza di esso i loro dati restano esclusi.
- Alcuni report hanno il campo **Area**. Compare solo agli amministratori,
  che con esso passano dai propri dati a tutto il team. Tutti gli altri vi
  vedono sempre i propri dati.
- **PDF** scarica una versione stampabile; **CSV** ed **Excel** si trovano in
  **Esportazione**. Le esportazioni riprendono i filtri impostati. Ogni
  esportazione viene registrata nel registro di audit.
- Le esportazioni richiedono il permesso **Esporta i report**, anche per
  **Esecuzioni di procedura bloccate**; senza il permesso i pulsanti di
  esportazione non compaiono. Gli amministratori possono sempre esportare.
  Restano libere le esportazioni che contengono solo i Suoi dati – vedere
  «Usare i report».

## Operazioni

**Report** → **Progetti e clienti** → **Operazioni** apre il **Report
operativo**: ordini di servizio (ordini del tipo di ordine Service), attività
e giri del periodo.

- Riquadri: **Ordini di servizio** con il tasso di completamento
  (**Completamento**), **Tempo di servizio Σ**, **Attività** con il numero di
  attività scadute e il loro tasso di completamento (il riquadro cambia colore
  non appena un’attività è scaduta) e **Giri** con chilometri e durata
  pianificati.
- Grafici: **Ordini di servizio: creati vs. completati per settimana** e
  **Backlog per cliente (top 15)** con gli ordini di servizio ancora aperti
  per cliente. Con il diritto **Visualizza i report** un clic su una barra
  apre i punti aperti del cliente; senza questo diritto le barre non sono
  cliccabili.
- Tabelle: **Ordini di servizio – stato**, **Ordini di servizio – priorità**,
  **Attività – stato**, **Attività – priorità** e **Giri – per dipendente**
  (giri, **Km pianificati**, **Durata pianificata**).

Gli ordini di servizio sono riuniti in quattro gruppi: **Aperto** (pianificato
o accettato), **In lavorazione**, **Problema** (in attesa di riscontro o di
materiale) e **Completato** (completato, collaudato o fatturato). Gli ordini
annullati contano nel totale, ma né in un gruppo né nel tasso di
completamento. Fa fede la data pianificata dell’ordine. Le attività contano
se sono state create, modificate o erano in scadenza nel periodo; le attività
archiviate restano escluse.

Filtri: **Area**, **Cliente**, **Progetto**, **Dipendente**, **Stato ordine**
e **Includi i clienti nascosti**. Cliente e progetto agiscono su ordini di
servizio e attività, il dipendente su tutti e tre gli ambiti; i giri non
conoscono né cliente né progetto. Lo **Stato ordine** restringe solo i
riquadri e le tabelle degli ordini di servizio.

Senza diritti di amministratore Lei vede gli ordini di servizio assegnati a
Lei, le attività assegnate a Lei o create da Lei e i Suoi giri. Esportazione
in PDF, CSV ed Excel.

## Ripartizione del tempo

**Report** → **Progetti e clienti** → **Ripartizione del tempo** apre la
pagina **Ripartizione del tempo per dimensione**. Mostra come le
registrazioni di tempo ripartite del periodo si distribuiscono su **Attività
(task)**, **Asset**, **Progetti**, **Centri di costo**, **Sedi**, **Veicoli**,
**Attività** e sulle dimensioni libere di **Dimensioni temporali**.

- Riquadri: **Tempo ripartito** e il numero di **Dimensioni**.
- Una scheda per dimensione con **Destinazione**, **Minuti** (mostrati in ore
  e minuti) e **Registrazioni** (numero di registrazioni di tempo), in ordine
  decrescente di tempo.
- La base dati sono esclusivamente le quote di ripartizione. Il tempo non
  ripartito compare negli altri report del tempo.

La pagina mostra l’intera organizzazione e non ha altri filtri. Compare nel
menu per gli amministratori e per i ruoli con il diritto **Visualizza i
report**. Esportazione in PDF, CSV ed Excel.

## Deviazioni di procedura

**Report** → **Progetti e clienti** → **Deviazioni di procedura** analizza le
deviazioni registrate durante l’esecuzione delle procedure nel periodo. La
voce di menu compare con il diritto **Visualizza gli scostamenti di
procedura**.

- Riquadri: **Deviazioni**, **Critiche**, **Quota con follow-up** (quota con
  un punto aperto o un ordine successivo) e **Ø ore fino alla decisione**
  (dalla creazione all’accettazione del rischio, solo deviazioni decise).
- Grafici: **Deviazioni per tipo**, le deviazioni nel tempo per gravità e
  **Procedure con più deviazioni (top 10)**.
- Elenco: **Data**, **Procedura**, **Passo**, **Tipo**, **Gravità**,
  **Follow-up** (**Punto aperto** o **Ordine successivo**), **Rischio
  accettato il** e **Ore fino a decisione**. L’icona a fine riga apre
  l’esecuzione della procedura.

Filtri: **Procedura**, **Tipo**, **Gravità**, **Rischio accettato** (**Solo
accettate** o **Solo aperte**) e **Misura di follow-up** (**Con punto
aperto/ordine successivo** o **Senza follow-up**). Esportazione in PDF, CSV ed
Excel; CSV ed Excel contengono inoltre l’azione proposta e la motivazione.

## Esecuzioni di procedura bloccate

**Report** → **Progetti e clienti** → **Esecuzioni di procedura bloccate**
mostra le esecuzioni in attesa di un tempo di attesa, di una seconda persona
o di una decisione sul rischio. La voce di menu compare con il diritto
**Visualizza le esecuzioni di procedura**.

- **Attualmente bloccate**: **Procedura**, **Motivo del blocco**, **Bloccata
  dal** e **Ore**, con un collegamento all’esecuzione. I motivi di blocco sono
  una deviazione critica senza decisione sul rischio, un tempo di attesa non
  ancora trascorso e una seconda persona mancante. All’apertura della pagina
  i tempi di attesa trascorsi vengono sbloccati.
- **Blocchi conclusi nel periodo**: per motivo di blocco e procedura
  **Numero**, **Ore medie** e **Più lungo (h)**.

Qui il periodo si imposta nel campo da–a della barra dei filtri; senza una
Sua indicazione vale la selezione del periodo nell’intestazione.
Esportazione in CSV ed Excel; contiene i blocchi conclusi.

## Materiali

**Report** → **Risorse** → **Materiali** apre la pagina **Consumo
materiale**. Si basa sulle righe di materiale dei fogli ore il cui giorno di
lavoro cade nel periodo.

- Grafici: **Valore di consumo per materiale (top 20)** e i costi del
  materiale nel tempo.
- Riquadri: **Materiali**, **Utilizzi** e **Netto Σ**.
- Tabella **Consumo per materiale**: **SKU**, **Materiale**, **Unità**,
  **Quantità**, **Utilizzi** e **Netto**, in ordine decrescente di importo
  netto. Lo stesso materiale in unità diverse compare su righe separate; le
  righe senza anagrafica materiale compaiono con la loro descrizione.

Filtri: **Area**, **Cliente**, **Progetto** e **Includi i clienti nascosti**;
il cliente agisce tramite il progetto del foglio ore. Senza diritti di
amministratore Lei vede solo i Suoi fogli ore. Esportazione in PDF, CSV ed
Excel.

## Servizio di emergenza

**Report** → **Risorse** → **Servizio di emergenza** apre il **Report servizio
di emergenza** con i turni di reperibilità e gli interventi effettivi per
dipendente, così come vengono gestiti nella **Lista di lavoro**. I tempi che
superano il periodo contano solo in proporzione; le voci archiviate non
contano.

- Riquadri: **Dipendenti**, **Reperibilità** (con il numero di turni),
  **Interventi attivi** (tempo di intervento con il numero di interventi) e
  **Quota attiva** (tempo di intervento rispetto al tempo di reperibilità).
- Grafici: **Reperibilità per collaboratore e settimana** come mappa di
  calore e gli interventi nel tempo.
- Tabella per dipendente: **Turni**, **Reperibilità**, **Interventi**,
  **Orario intervento** e **Quota attiva** con una riga dei totali.

Filtri: **Area** (**Solo la mia reperibilità** o **Tutto il team**, solo per
gli amministratori), **Dipendente** e **Team**. Esportazione in PDF (con la
mappa di calore), CSV ed Excel.

## Analisi prodotto

**Report** → **Progetti e clienti** → **Analisi prodotto** mostra difetti,
punti aperti e impegno per asset, gruppo di prodotti o modello. La voce di
menu compare per gli amministratori e con il diritto **Visualizza i report**.

- **Livello**: **Per asset**, **Per gruppo di prodotti** o **Per modello**;
  altri filtri sono **Gruppo di prodotti**, **Produttore**, **Cliente** e
  **Includi i clienti nascosti**.
- Colonne: **Asset**, **Ordini** (ordini relativi all’asset creati nel
  periodo), **Punti aperti** (attualmente aperti, indipendentemente dal
  periodo), **Escalato** (punti aperti nello stato **Bloccato**), **Difetti**
  (verbali di difetto di questi ordini nel periodo), **Tasso di difetti %**
  (difetti rispetto agli ordini) e **Ultimo incidente**.
- Se nel periodo esistono registrazioni di tempo da sessioni di
  manutenzione remota per i dispositivi, si aggiungono **Sessioni di
  manutenzione** e **Tempo di manutenzione** e un grafico del tempo di
  manutenzione.
- Grafici: **Difetti nel periodo (top 20)** e **Tasso di difetti (top 15)**. I
  numeri di punti aperti, escalation e difetti e le barre portano agli elenchi
  di dettaglio corrispondenti.

Esportazione in PDF, CSV ed Excel.

## Dettagli progetto

**Report** → **Progetti e clienti** → **Dettagli progetto** mostra ore e
ricavi di un singolo progetto per mese. Il report riguarda l’anno solare in
cui inizia il periodo scelto.

- Scelga **Cliente** e **Progetto**. Senza selezione compare il primo
  progetto dell’elenco. Il filtro **Dipendente** esiste solo con una visione
  dei tempi estesa a tutta l’organizzazione.
- La scheda del progetto indica i totali annui **Σ ore** e **Σ €** ed elenca
  **Mese**, **Ore** e **Ricavo**; segue la **Ripartizione per dipendente**. Il
  ricavo è la somma degli importi memorizzati con le registrazioni di tempo.
- Grafici: **Andamento delle ore nel periodo**, **Ore effettive e pianificate
  al mese** (piano ricavato dal campo **Durata prevista (HH:MM)** degli ordini
  del progetto in base al loro inizio, in mancanza dalla durata dell’intervento di un ordine pianificato, altrimenti dalla durata della
  finestra oraria o dell’appuntamento; con un dipendente selezionato solo gli ordini
  assegnati a quella persona, senza una visione dei tempi estesa a tutta
  l’organizzazione solo quelli assegnati a Lei; senza dati di piano una linea
  mostra la mediana dei mesi effettivi) e **Ore per tipo di ordine al mese**.

Gli amministratori e i ruoli con **Visualizza tutte le registrazioni di
tempo** vedono tutti i progetti e tutte le ore. Tutti gli altri vedono solo i
progetti su cui hanno registrato tempo personalmente, e lì solo le proprie
ore. Esportazione in PDF, CSV ed Excel non appena è selezionato un progetto.

## Progetti inattivi

**Report** → **Progetti e clienti** → **Progetti inattivi** elenca tutti i
progetti non archiviati dell’organizzazione su cui nel periodo non è stato
registrato tempo.

- Grafico **Progetti per durata di inattività**: **≤ 3 mesi**, **3–6 mesi**,
  **6–12 mesi**, **> 12 mesi** e **Senza registrazioni**, misurati
  dall’ultima registrazione di tempo fino alla fine del periodo.
- Tabella: **Progetto**, **Cliente**, **Stato** e **Ultima attività** (la
  registrazione di tempo più recente in assoluto).
- Filtro: **Cliente**.

Per fare ordine, selezioni i progetti e scelga **Archivia selezionati**;
confermi la richiesta con **Archivia**. Vengono archiviati solo i progetti
creati da Lei; gli amministratori possono archiviarli tutti. Il messaggio
indica il numero di progetti effettivamente archiviati. Esportazione in CSV
ed Excel.

## Qualità dei dati

**Report** → **Progetti e clienti** → **Qualità dei dati** apre la pagina
**Qualità dei dati: classificazioni obbligatorie**. Elenca gli ordini del
periodo a cui mancano indicazioni richieste dalle regole obbligatorie di
**Classificazioni**. La voce di menu e la pagina richiedono il diritto
**Visualizza i report**.

- Riquadri: **Ordini con lacune**, **Lacune bloccanti** (regole bloccanti) e
  **Lacune minori** (avvisi).
- Grafici: gli ordini con lacune di classificazione nel tempo e
  **Classificazioni mancanti per cliente (top 15)**.
- **Per dominio** e **Per fase**: dove si trovano le lacune e da quale fase
  l’indicazione è richiesta (alla creazione, prima del completamento o prima
  della firma).
- **Ordini interessati**: **Ordine**, **Data** e **Classificazioni mancanti**
  (rosso = bloccante, giallo = minore). **Registra a posteriori** apre
  l’ordine.

Filtri: **Cliente**, **Progetto**, **Tipo di ordine** e **Includi i clienti
nascosti**. Vengono controllati al massimo i 1.000 ordini non archiviati più
recenti del periodo. La pagina non modifica nulla e non offre esportazioni.
