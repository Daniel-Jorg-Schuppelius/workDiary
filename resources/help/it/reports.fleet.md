---
title: "Parco veicoli, registro viaggi e tempi di guida"
topic: reports.fleet
version: 1
keywords:
    - analisi veicoli
    - chilometraggio
    - costi carburante
    - costo per chilometro
    - registro viaggi fiscale
    - viaggi privati
    - benefit in natura
    - regola dell’1 per cento
    - auto aziendale
    - tempi di guida
    - tempi di riposo
    - interruzione di guida
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - assets.fleet
    - travel-expenses.manage
    - fleet.license-checks
    - reports.arbzg-compliance
    - admin.organization-settings
    - reports.overview
---

Questi report riguardano veicoli e viaggi: chilometri e costi energetici per
veicolo, il registro viaggi fiscale di un veicolo, il confronto tra il metodo
del libretto di viaggio e la regola dell’1 % e la prova dei tempi di guida e
di riposo. Si basano sui viaggi del **Registro viaggi** (**Trasferte e
spese** → **Registro viaggi**), sui giustificativi del **Registro
rifornimenti e ricariche** (**Parco veicoli** → **Registro rifornimenti e
ricariche**) e sui dati dei veicoli in **Parco veicoli** → **Veicoli**.

## Periodo ed esportazione

- Il periodo si sceglie con la selezione del periodo nell’intestazione. Il
  confronto 1 % calcola invece su un anno solare.
- **PDF** scarica una versione stampabile; **CSV** ed **Excel** si trovano in
  **Esportazione**. Le esportazioni riprendono i filtri scelti; le
  esportazioni PDF e CSV vengono registrate nel registro di audit.

## Parco veicoli

**Report** → **Risorse** → **Parco veicoli** apre il **Report parco veicoli**
con viaggi, rifornimenti, costi energetici e rimborsi per veicolo.

- Riquadri: **Veicoli**, **Σ km** (con il numero di viaggi), **Rifornimenti /
  ricariche** (con litri e kWh), **Costi energetici** (con il totale dei
  rimborsi) e **Media €/km**.
- Grafici: **Chilometri per veicolo (top 15)** e i chilometri nel tempo.
- Tabella per veicolo: **Veicolo**, **Propulsione**, **Viaggi**, **km**,
  **Rimborso**, **Rifornimenti**, **Litri**, **kWh**, **Costi energetici**,
  **€/km** e **Chilometraggio**, con una riga dei totali.

Ecco come si formano i valori:

- **Viaggi**, **km** e **Rimborso** provengono dai viaggi del registro a cui è
  assegnato un veicolo e la cui data cade nel periodo.
- **Rifornimenti**, **Litri**, **kWh** e **Costi energetici** provengono dai
  giustificativi di rifornimento e ricarica iniziati nel periodo.
- **€/km** divide i costi energetici per i chilometri; senza chilometri o
  senza costi il campo resta vuoto.
- **Chilometraggio** è l’ultima lettura del contachilometri dai giustificativi
  di rifornimento e ricarica del periodo, altrimenti quella memorizzata nel
  veicolo.

Filtri: **Area** (**Solo i miei viaggi** o **Intero parco veicoli**, solo per
gli amministratori) e **Dipendente**. Tutti gli altri vedono solo i propri
viaggi e giustificativi. Esportazione in PDF, CSV ed Excel.

## Prova libretto di viaggio

**Report** → **Risorse** → **Prova libretto di viaggio** mostra il registro
viaggi fiscale di un veicolo: letture del contachilometri, tipo di viaggio,
destinazione, scopo e conducente, totali per tipo di viaggio e quota privata.

- Scelga il **Veicolo**. I veicoli in **Modalità libretto** compaiono in alto
  e sono contrassegnati di conseguenza. Senza diritti di amministratore
  l’elenco contiene i veicoli senza **Conducente predefinito** e quelli di cui
  Lei è il conducente predefinito; gli amministratori vedono tutti i veicoli.
- Se il veicolo non è in modalità libretto, un avviso ricorda che i viaggi
  senza letture del contachilometri e senza blocco non costituiscono un
  registro viaggi ai fini fiscali. La modalità si attiva sul veicolo con
  **Modalità libretto (fiscale)**.
- Riquadri: **Viaggi** (con il numero di viaggi bloccati), **Σ km**, i
  chilometri per tipo di viaggio (**Aziendale**, **Casa–lavoro**, **Privato**)
  e **Quota privata** (chilometri privati rispetto a tutti i chilometri).
- Tabella: **Data**, **Km inizio**, **Km fine**, **km**, **Tipo di viaggio**,
  **Destinazione**, **Scopo**, **Conducente** e **Stato** (**bloccato**,
  **aperto** o **stornato**, oltre a **firmato** e **Viaggio di storno**). La
  riga finale indica i chilometri per tipo di viaggio.

I chilometri di un viaggio risultano dai km finali meno quelli iniziali; se
mancano le letture, conta la distanza registrata, raddoppiata per andata e
ritorno. L’elenco contiene tutti i viaggi del veicolo nel periodo, anche
quelli di altri conducenti. I viaggi originali stornati restano visibili
barrati, ma non rientrano in alcun totale.

Esportazione in PDF, CSV ed Excel non appena è scelto un veicolo. CSV ed
Excel contengono inoltre l’indirizzo di partenza, gli orari di blocco e di
firma, il contrassegno di storno, il viaggio corretto con il motivo della
correzione, nonché i totali e la quota privata.

## Confronto 1 %

Il **Confronto 1 %** si apre con il pulsante omonimo nella pagina **Prova
libretto di viaggio**; non ha una voce di menu propria. Per ogni veicolo
mette a confronto il benefit in natura secondo il metodo del libretto di
viaggio e la regola dell’1 %. Si tratta di un calcolo semplificato e non di
una consulenza fiscale.

- **Anno**: l’anno in corso e i sei anni precedenti; è preselezionato l’anno
  precedente.
- Sono elencati solo i veicoli in modalità libretto, con la stessa selezione
  di veicoli della prova libretto di viaggio.
- **mesi**: mesi con viaggi. **km totali**, **di cui privati** e **di cui
  casa–lavoro** contano solo i viaggi con km iniziali e finali; i viaggi
  originali stornati non contano.
- **Costi totali**: costi energetici dai giustificativi di rifornimento e
  ricarica dell’anno più gli altri costi annuali; il tooltip mostra le due
  parti.
- **Metodo del libretto di viaggio**: costi totali moltiplicati per la quota
  dei chilometri privati e casa–lavoro su tutti i chilometri.
- **Regola dell’1 %**: **Prezzo di listino lordo (€)**, arrotondato per
  difetto al centinaio di euro, di cui l’1 % per mese di utilizzo più lo
  0,03 % per chilometro di **Distanza casa–lavoro (km)** e per mese. Per i
  veicoli elettrici e gli ibridi agevolati acquistati dal 2019, la base scende
  a un quarto o alla metà a seconda della **Data di acquisto** e del prezzo di
  listino; la cella mostra allora **Base** con 0,25 % o 0,5 % invece dell’1 %. Senza prezzo di
  listino compare **Prezzo di listino mancante**.
- **Più conveniente** contrassegna il metodo con il valore più basso.

Il simbolo dell’euro apre la finestra **Altri costi annuali** per leasing,
assicurazione, bollo auto, manutenzione, riparazioni e ammortamento, con
**Importo (€)** e **Nota**. Per questo serve il diritto **Gestisci i
veicoli**. La pagina non offre esportazioni.

## Prova tempi di guida

La **Prova tempi di guida** è un download dei tempi di guida e di riposo per
conducente e giorno di calendario. La trova nella pagina **Conformità orario
di lavoro** (**Report** → **Finanze e audit**) nel menu **Esportazione**.
Compare solo se nelle impostazioni di conformità dell’organizzazione, alla
voce **Tempi di guida e di riposo**, le regole sui tempi di guida sono
attivate, e richiede il diritto **Visualizza la conformità orario di
lavoro**.

- Analizza i viaggi effettivi con orario di partenza e di arrivo sui veicoli
  per cui è impostato **Applicare le regole sui tempi di guida e di riposo**.
  I dati del tachigrafo non vengono letti.
- Colonne: **Conducente**, **Matricola**, **Data**, **Veicoli**, **Prima
  partenza**, **Ultimo arrivo**, **Tempo di guida**, **Periodo di guida più
  lungo senza interruzione**, **Interruzioni (min)**, **Riposo precedente** e
  i **Rilievi** del giorno.
- Il download riprende il periodo e il filtro dipendente della pagina e
  fornisce un file CSV. Viene registrato nel registro di audit.

I rilievi si basano sui limiti del regolamento (CE) 561/2006 e della FPersV:
al massimo 9 h di guida al giorno (10 h due volte a settimana), 56 h a
settimana e 90 h nell’arco di due settimane, un’interruzione di 45 minuti
dopo 4,5 h (frazionabile in 15 e 30 minuti), 11 h di riposo giornaliero (al
massimo tre volte a settimana 9 h) e 45 h di riposo settimanale (24 h con
compensazione). Non si tratta di consulenza legale; spetta all’azienda
stabilire quali norme si applicano nel singolo caso.
