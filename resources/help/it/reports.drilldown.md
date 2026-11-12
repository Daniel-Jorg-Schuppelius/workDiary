---
title: "Drilldown dall'indicatore ai singoli casi"
topic: reports.drilldown
version: 3
keywords:
    - dettaglio KPI
    - approfondimento
    - dati sottostanti
    - elenco dettagliato
    - scomporre un indicatore
    - punti aperti per cliente
    - protocolli di difetto
    - difetti ricorrenti
    - cause
    - esportare il report
    - analisi di dettaglio
    - risalire ai dati
    - drill-down
audience: []
related:
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.operations
---

Nell'**analisi dei clienti**, nell'**analisi dei tipi di ordine** e
nell'**analisi prodotto** i valori sono cliccabili. Il drilldown elenca
i singoli casi dietro il valore, con il periodo dell'intestazione della
pagina e i filtri attivi al momento del clic. L'intestazione indica il
cliente, il tipo di ordine o l'ambito e il periodo; **All'analisi dei
clienti**, **All'analisi dei tipi di ordine** o **All'analisi dei
prodotti** riporta all'analisi. Le pagine richiedono il permesso
«Visualizza i report».

## Punti aperti

**Dettaglio: punti aperti** mostra i punti ancora aperti: sul cliente
stesso, sui suoi ordini e progetti, sugli ordini di un tipo di ordine o
sugli asset dell'ambito scelto (in tal caso con la colonna **Asset**).
Colonne: **Titolo**, **Stato**, **Gravità**, **In scadenza** e
**Assegnato**; i punti modificati per ultimi stanno in alto. Un clic
sulla colonna **Escalato** apre l'elenco con **Solo con escalation**:
compaiono allora solo i punti bloccati.

## Protocolli di difetto

**Dettaglio: protocolli di difetto** elenca i protocolli di difetto
sugli ordini del periodo; nell'analisi dei clienti si trova dietro la
colonna **Rilavorazione**. Colonne: **Titolo**, **Stato**, **Momento**,
**Creato da** e **Ordine**; un clic apre l'ordine. I protocolli più
recenti stanno in alto.

## Cause e difetti ricorrenti

**Dettaglio: causa (tipo di ordine)** mostra due elenchi per una causa
dell'analisi dei tipi di ordine: **Rilavorazioni con questo motivo**
(data, ordine, dipendente, minuti) e **Reclami con questa causa
principale** (numero, ordine, stato).

**Drill-down: difetti ricorrenti** indica per asset le segnalazioni di
difetto del periodo e degli ultimi **12 mesi** e i gradi di gravità. Da tre
difetti in dodici mesi la riga porta il contrassegno **Difetto ricorrente**;
il nome dell'asset apre il fascicolo dell'oggetto. Vi si accede dal numero
nella colonna **Difetti ricorrenti** dell'analisi prodotto: mostra allora
solo i casi di difetto ricorrente della riga cliccata, e l'intestazione
indica l'area e **Solo difetti ricorrenti**.

## Esportazione

Esporta i punti aperti e i protocolli di difetto in PDF, CSV o Excel, i
difetti ricorrenti in CSV o Excel. L'esportazione contiene le stesse
righe dell'elenco e indica periodo e filtri. La pagina delle cause non
ha esportazione.
