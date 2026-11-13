---
title: "Scadenze dei clienti"
topic: customers.deadlines
version: 1
keywords:
    - Scadenze dei clienti
    - Scadenze
    - Dichiarazione IVA periodica
    - Proroga permanente
    - Ritenute sui salari
    - Elenco riepilogativo
    - Dichiarazione annuale
    - Prospetto dei contributi
    - Visita di consulenza
    - Ambito dei servizi
audience: []
related:
    - contacts.manage
    - work-queue.overview
    - delegations.manage
    - accounting.filings
---

Alcune scadenze non dipendono da un contratto ma dalle caratteristiche del
cliente e dalla legge: la dichiarazione IVA periodica di un cliente dello
studio, il prospetto dei contributi di un cliente paghe, la visita di
consulenza di un cliente dell’assistenza. workDiary calcola queste **scadenze
dei clienti** dalle caratteristiche e dall’ambito dei servizi del cliente,
avvisa per tempo e le spunta non appena l’ordine corrispondente è completato.
L’interfaccia dice «cliente»; in workDiary «mandante» indica l’organizzazione.

## Set di regole

Un **set di regole** contiene le norme di legge di un settore. È attivo quando
lo porta il profilo di settore o quando lo attivate in **Scadenze dei clienti ›
Impostazioni**.

- **Consulenza fiscale:** dichiarazione IVA periodica (10° giorno dopo il
  periodo, un mese dopo con proroga permanente), acconto speciale (10 febbraio
  per chi dichiara mensilmente con proroga), ritenute sui salari (10° giorno,
  mai prorogate), elenco riepilogativo (25° giorno, mai prorogato),
  dichiarazioni annuali per imposta in base all’ambito dei servizi (con
  consulente fino a fine febbraio del secondo anno successivo, altrimenti 31
  luglio; termini transitori fino al 2024) e – con il servizio **paghe** –
  prospetto e scadenza dei contributi previdenziali (terzultimo giorno
  lavorativo bancario, prospetto due giorni lavorativi bancari prima). Se una
  scadenza fiscale cade di fine settimana o in un giorno festivo **nella sede
  dell’ufficio delle imposte**, vale il giorno lavorativo successivo; con più
  casse malattia vale la data più vicina fra tutti i Land.
- **Assistenza domiciliare:** visita di consulenza ai sensi del § 37 c. 3 SGB
  XI – obbligatoria solo con l’assegno di cura e grado da 2 a 5, una volta a
  semestre; con grado 4 e 5, su richiesta, una volta a trimestre. workDiary
  legge il grado dalla classificazione del cliente.

## Gestire le caratteristiche

Nel **cockpit del cliente**, la scheda **Scadenze dei clienti** mostra le
caratteristiche e le prossime scadenze aperte. **Caratteristiche delle
scadenze** apre la finestra, precompilata come la versione in vigore («come
l’anno scorso»). Una modifica vale dal giorno scelto e chiude la versione
precedente; le scadenze passate conservano la loro base. workDiary ricalcola
poi solo le scadenze **aperte**; quelle completate, prorogate o richieste in
anticipo restano invariate. Nulla di quanto scaduto prima dell’inizio della
prima versione viene creato.

Se manca il **Land dell’ufficio delle imposte** o degli enti di riscossione,
workDiary usa la regione dei festivi dell’organizzazione e segnala la scadenza.

In **Ambito dei servizi** compaiono i servizi del cliente (tipo di voce e
gruppo di prodotti, obbligatorio o facoltativo, tipo di periodo, validità). Il
set consulenza fiscale ne ricava le dichiarazioni annuali e le paghe; il piano
dei servizi li mostra per periodo.

## Lavorare sulle scadenze

Un clic su una scadenza la apre con la cronologia. Potete segnarla come
**completata**, **prorogarla** (nuova data e motivo), indicarla come
**richiesta in anticipo** (l’ufficio delle imposte vuole la dichiarazione
prima, § 149 c. 4 AO), dichiararla **non dovuta** o **riaprirla**. Un ordine
completato con periodo completa da sé la scadenza corrispondente, ad esempio un
ordine **visita di consulenza** 2026-H1 la visita del primo semestre.

## Panoramica, promemoria e sostituzioni

- **Scadenze dei clienti** elenca tutte le scadenze con semaforo, filtri per
  set di regole, tipo, responsabile, mese e stato, ed esportazione CSV o Excel.
- La **persona responsabile** riceve un promemoria raggruppato quando le
  scadenze entrano nel preavviso (7 giorni predefiniti, regolabili per set) e
  un altro quando sono superate. Le scadenze fino al fine settimana e quelle
  superate compaiono nella sua **coda di lavoro** e nel **calendario**.
- Durante una **sostituzione**, chi sostituisce vede queste scadenze e
  promemoria con l’indicazione «In sostituzione di …». In caso di uscita o
  passaggio definitivo, la responsabilità passa al successore.

Possono vedere le scadenze le persone con il permesso **Visualizzare le
scadenze dei clienti**; caratteristiche, servizi e tutte le scadenze li gestisce
chi ha **Gestire le scadenze dei clienti e le relative caratteristiche**. La
persona responsabile lavora sulle proprie scadenze anche con il permesso di
lettura.

## Cosa workDiary non fa

Non trasmette nulla a ELSTER, DATEV o alle casse di assistenza e non tiene la
contabilità dei clienti. Le scadenze concordate restano obblighi contrattuali,
quelle delle apparecchiature piani di controllo.
