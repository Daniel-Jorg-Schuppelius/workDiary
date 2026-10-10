---
title: "Fatturazione, spese, pagamenti e fatturato"
topic: reports.billing
version: 3
keywords:
    - crediti aperti
    - scadenzario
    - tempo non fatturato
    - fatturato per cliente
    - livelli di sollecito
    - tasso di accettazione preventivi
    - riepilogo spese
    - compensi esterni
    - pagare i freelance
    - prevedere le maggiorazioni
    - fatturato per articolo
    - fatturato per categoria
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
    - personalverwaltung
    - teamleitung
related:
    - invoices.manage
    - finance.dunning
    - finance.incoming-invoices
    - travel-expenses.manage
    - org.members
    - admin.surcharge-rules
    - articles.master
    - reports.economics
---

Questi report riuniscono il denaro legato a prestazioni e personale: stato
delle fatture e dei crediti aperti, tempo non ancora fatturato, spese,
pagamenti ai collaboratori esterni, maggiorazioni previste dal piano turni e
fatturato per articolo. Trova la maggior parte delle pagine in **Report** →
**Finanze e audit**, la **Previsione maggiorazioni** in **Report** →
**Team**.

## Periodo ed esportazione

- Il periodo si sceglie con la selezione del periodo nell’intestazione. La
  **Previsione maggiorazioni** guarda invece in avanti a partire dal mese in
  corso.
- **PDF** scarica una versione stampabile; **CSV** ed **Excel** si trovano in
  **Esportazione**; i formati disponibili sono indicati per ogni report. Le
  esportazioni riprendono i filtri impostati. Ogni esportazione viene
  registrata nel registro di audit.

## Fatturazione

**Report** → **Finanze e audit** → **Fatturazione** apre il **Report
fatturazione**. Si apre per gli amministratori e per i ruoli con il diritto
**Visualizza tutte le registrazioni di tempo**; senza questo diritto
l’accesso viene negato.

- Riquadri:
  - **Emesso + pagato (Σ lordo)**: totale lordo delle fatture nello stato
    **Emessa**, **Parzialmente pagata** o **Pagata** la cui data fattura cade
    nel periodo (senza data fattura conta la data di creazione).
  - **Crediti aperti**: importo ancora aperto di tutte le fatture nello stato
    **Emessa** o **Parzialmente pagata**, indipendentemente dal periodo. I
    pagamenti ricevuti e le ritenute a garanzia aperte sono detratti; fatture
    proforma, note di credito e documenti di storno non contano. Il riquadro
    diventa rosso non appena una di esse è scaduta da più di 30 giorni;
    l’indicazione ne riporta il numero.
  - **Tempo non fatturato**: registrazioni di tempo fatturabili del periodo
    non ancora utilizzate da alcun percorso di fatturazione, con il numero di
    registrazioni e il ricavo previsto dagli importi memorizzati.
- Grafici: ore fatturabili e non fatturabili nel tempo e **Fatturato per
  cliente (top 15)** dalle fatture locali e dai documenti rispecchiati dal
  programma di contabilità. Un clic su un cliente apre **Clienti e progetti**
  per quel cliente.
- **Fatture per stato**: **Quantità**, **Netto** e **Lordo** per stato.
- **Scadenzario – partite aperte**: le fatture aperte in base ai giorni oltre
  la scadenza (senza scadenza, dalla data fattura) nelle fasce **Attuale**,
  1–7, 8–14, 15–30 e oltre 30 giorni, ciascuna con l’importo aperto, e
  **Totale aperti**.
- **Clienti principali (emesso + pagato nel periodo)**: **Cliente**,
  **Fatture** e **Lordo**; se alcuni importi provengono dal programma di
  contabilità, compare un’ulteriore colonna **di cui programma contabile**.
  Le fatture parzialmente pagate sono incluse.
- **Fatture elettroniche in entrata (nel periodo)**: documenti in entrata per
  stato con numero e importo lordo, nonché il numero di quelli trasferiti
  alla contabilità.
- **Validazione in entrata & livelli di sollecito**: **Validazione
  verificata**, **Validazione superata**, **Validazione non riuscita** e le
  fatture aperte per livello di sollecito da 1 a 3.
- **Preventivi e catena documentale (nel periodo)**: preventivi per stato,
  **Tasso di accettazione**, **Mediana creazione → decisione** in giorni,
  **Preventivo → fattura**, **Pro forma → fattura**, **Storni / note di
  credito** e **Tasso di correzione**.

Filtri: **Cliente**, **Progetto**, **Dipendente** e **Includi i clienti
nascosti**. Cliente e progetto agiscono su fatture, preventivi e tempi, il
dipendente solo sui tempi. Le fatture in entrata e i livelli di sollecito
valgono sempre per l’intera organizzazione. Con un progetto selezionato, gli
importi dal programma di contabilità vengono esclusi, perché questi
documenti non conoscono alcun progetto. Esportazione in PDF, CSV ed Excel.

## Spese

**Report** → **Finanze e audit** → **Spese** apre il **Report spese**: spese
per dipendente e categoria nel periodo, calcolate con importi lordi in base
alla data della spesa.

- Grafici: spese per mese (o settimana o giorno) per categoria, con le quattro
  categorie maggiori singolarmente e il resto raggruppato, e **Principali
  generatori (top 15)**.
- Riquadri: **Totale (lordo)**, **Dipendenti**, **Categorie** e **mesi**.
- Tabella con una riga per **Dipendente** e **Categoria**, una colonna per
  mese e il **Totale**, seguita da **Categorie principali**.

Filtri: **Area** (**Solo i propri** o **Intera organizzazione**, solo per gli
amministratori), **Dipendente**, **Team**, **Progetto** e **Stato**. Senza
diritti di amministratore Lei vede solo le Sue spese. La pagina non offre
esportazioni.

## Pagamenti esterni

**Report** → **Finanze e audit** → **Pagamenti esterni** calcola gli importi
da pagare ai collaboratori esterni nel periodo. La voce di menu compare con
il diritto **Gestisci i dati del personale e delle buste paga**.

Vengono considerati i dipendenti il cui **Modello di retribuzione** è
impostato su **Forfettario** o **A consuntivo**:

- **Forfettario** con intervallo **Mensile**: **Importo forfettario (€)** per
  il numero di mesi del periodo.
- **Forfettario** con intervallo **Per intervento**: importo forfettario per
  il numero di giorni con registrazioni di tempo.
- **Forfettario** con intervallo **Una tantum**: l’importo forfettario una
  sola volta.
- **A consuntivo**: tempo registrato per **Tariffa di retribuzione (€/h)**.

La tabella mostra **Dipendente**, **Modello**, **Base di calcolo** e
**Importo** con un totale generale. I grafici mostrano i pagamenti nel tempo
e **Pagamenti per esterno (top 15)**. Nell’andamento un forfait mensile compare
una volta al mese, nella sezione che contiene il primo giorno di quel mese
compreso nel periodo; il totale del grafico corrisponde quindi alla tabella. Tutti gli importi sono lordi, esclusi
imposte e contributi. Filtro: **Dipendente**. La pagina non offre
esportazioni.

## Previsione maggiorazioni

**Report** → **Team** → **Previsione maggiorazioni** stima i minuti di
maggiorazione per mese e tipo di retribuzione sulla base dei turni
pianificati nel **Piano turni**. La voce di menu compare per gli
amministratori e con il diritto **Visualizza i report**.

- **Mesi**: 3, 6 o 12 mesi a partire dal mese in corso.
- **Dipendente**: tutti i dipendenti attivi o una persona.
- Tabella: **Tipo retribuzione**, **Regola**, una colonna per mese e
  **Totale**, con una riga dei totali.

Il calcolo usa le **Regole di maggiorazione** attive; i turni annullati non
contano. È solo un’anteprima senza contesto di sede: le regole che dipendono
dalla sede si applicano solo al momento della timbratura. Il conteggio
avviene esclusivamente tramite l’esportazione dei tempi. Esportazione in CSV
ed Excel.

## Fatturato per prodotto

**Report** → **Finanze e audit** → **Fatturato per prodotto** mostra
quantità, fatturato netto e quota per articolo. La voce di menu compare per
gli amministratori e con il diritto **Visualizza tutte le registrazioni di
tempo**.

- Base dati: righe di fatture locali, fatture di acconto e fatture finali la
  cui data fattura cade nel periodo e il cui stato è **Emessa**,
  **Parzialmente pagata** o **Pagata**; note di credito e documenti di storno
  riducono i valori con una quantità negativa. Si aggiungono le fatture e le
  note di credito rispecchiate dal programma di contabilità. I documenti
  trasferiti da una fattura locale contano una sola volta, bozze e documenti
  annullati per nulla.
- Riquadri: **Fatturato netto totale**, **di cui dal programma contabile**,
  **Articoli con fatturato** e **Quota senza riferimento articolo** (righe
  senza articolo selezionato).
- Grafico degli articoli con il fatturato più alto; quanti ne mostra lo
  stabilisce con **Top N nel grafico** (da 3 a 50, predefinito 10). Un clic
  apre l’articolo.
- **Ricavo per categoria**: **Categoria**, **Articolo**, **Fatturato netto**
  e **Quota**.
- Tabella: **Numero articolo**, **Articolo**, **Quantità**, **Unità**,
  **Fatturato netto**, **Quota**, **Documenti** e **Fonte** (**locale** o il
  nome della contabilità collegata). Le righe senza articolo sono raggruppate
  in **senza riferimento articolo**.

Esportazione in PDF, CSV ed Excel.
