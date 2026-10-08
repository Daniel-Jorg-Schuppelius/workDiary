---
title: "I miei report"
topic: reports.my-reports
version: 1
keywords:
    - Il mio mese
    - Il mio anno
    - bilancio lavoro
    - ore personali
    - riepilogo ore
    - riepilogo mensile
    - riepilogo annuale
    - controllare gli straordinari
    - confronto previsto effettivo
    - stampare il foglio ore
    - saldo
audience: []
related:
    - reports.overview
    - reports.attendance
    - time-accounts.flex
    - attendance.manage
    - time-entries.edit
---

In **Report** → **Personale** ogni persona dispone di tre report sul proprio
tempo: **Il mio mese**, **Il mio anno** e **Bilancio lavoro**. Mostrano
esclusivamente le Sue registrazioni. Si basano sulle Sue registrazioni di tempo;
il bilancio lavoro usa inoltre le Sue timbrature e il Suo modello di orario di
lavoro. I report non sono una fonte di dati autonoma: se un valore non è
corretto, corregga la registrazione di tempo o la timbratura – al prossimo
accesso il report ricalcola i valori.

## Scegliere il periodo

Le tre pagine seguono il periodo selezionato nell'intestazione. Faccia clic
sull'icona del calendario (**Seleziona periodo**) e scelga in **Selezione
rapida** ad esempio **Questo mese**, **Mese scorso** o **Quest’anno**. Le frecce
**Periodo precedente** e **Periodo successivo** spostano il periodo indietro o
avanti; sugli schermi più grandi può anche inserire nell'intestazione una data
di inizio e di fine personalizzata e confermare con **Applica**. Il periodo
attivo è indicato nella barra dei filtri della pagina.

- **Il mio mese** mostra sempre il mese di calendario in cui inizia il periodo.
- **Il mio anno** mostra l'anno di calendario in cui inizia il periodo.
- **Bilancio lavoro** valuta il periodo esattamente dal primo all'ultimo giorno.

## Il mio mese

**Report** → **Personale** → **Il mio mese** elenca giorno per giorno tutte le
Sue registrazioni di tempo del mese.

- Ogni giorno inizia con una riga di intestazione con la data, la durata totale
  e il ricavo totale del giorno; le domeniche sono evidenziate in rosso.
- Seguono le registrazioni con le colonne **Tempo** (inizio e fine), **Tipo**,
  **Cliente / progetto**, **Attività / descrizione** (compito e descrizione),
  **Durata** e **Ricavo**. Il ricavo è l'importo calcolato per la
  registrazione; il tempo non fatturabile compare con 0 €.
- Sopra la tabella si trovano i totali mensili di ore e ricavo, in fondo la riga
  **Totale**.
- Due grafici: **Ore al giorno** come andamento nel mese e **Ore alla settimana
  per tipo**, impilate per tipo per ogni settimana di calendario.

Filtri: **Cliente**, **Progetto** e **Tipo** con **Tutti**, **Lavoro**,
**Trasferta** e **Reperibilità**. Una selezione ha effetto immediato;
**Reimposta** rimuove tutti i filtri.

Esportazione: il pulsante **PDF** genera l'elenco giornaliero con i totali e un
grafico delle ore al giorno. Il menu **Esportazione** offre **CSV** ed **Excel**
con una riga per registrazione: data, inizio, fine, tipo, cliente, progetto,
compito, descrizione, minuti e ricavo. Tutte le esportazioni applicano i filtri
impostati.

## Il mio anno

**Report** → **Personale** → **Il mio anno** mostra le Sue ore sull'intero anno
di calendario.

- Il riquadro **Totale annuale** indica le ore dell'anno.
- La heatmap **Ore al giorno** ha una riga per mese e una colonna per giorno
  (da 1 a 31). Più intenso è il colore di una cella, più ore contiene; la scala
  dei colori si basa sul valore giornaliero più alto dell'anno. Passando sopra
  una cella compaiono data e ore, le domeniche sono segnate in rosso.
- Il grafico a barre **Ore al mese** mostra i totali mensili.
- Un clic sul nome di un mese nella heatmap o su una barra apre **Il mio mese**
  esattamente per quel mese, con gli stessi filtri.

Filtri: **Cliente**, **Progetto** e **Tipo** come in **Il mio mese**. Questa
pagina non prevede esportazioni.

## Bilancio lavoro

**Report** → **Personale** → **Bilancio lavoro** confronta per il periodo
scelto il tempo previsto, la presenza e il tempo registrato. I riquadri in alto:

- **Previsto**: tempo previsto dal Suo modello di orario di lavoro. I giorni
  festivi e i giorni di ferie approvate non hanno tempo previsto.
- **Presenza**: le Sue timbrature meno le pause. Le timbrature annullate non
  contano; una timbratura ancora in corso viene conteggiata fino al momento
  attuale.
- **Registrato**: le Sue registrazioni di tempo dei tipi lavoro e trasferta. La
  reperibilità e le registrazioni con l'attività **Pausa** o **Assenza** non
  contano.
- **Non distribuito**: presenza non ancora coperta da registrazioni di tempo
  (presenza meno tempo registrato, mai negativo).
- **Saldo**: tempo registrato meno tempo previsto – verde se positivo, rosso se
  negativo.

Sotto si trovano i grafici **Ore effettive e previste al giorno** (per periodi
oltre 62 giorni **Ore effettive e previste per settimana**) e **Ore effettive e
previste al mese**, ciascuno con una linea della mediana. Il blocco
**Distribuzione per attività** indica le ore registrate per attività. La
tabella mostra per ogni giorno **Data**, **Previsto**, **Presenza**, **Pausa**,
**Registrato**, **Non distribuito** e **Saldo** e la riga **Totale**; i giorni
senza tempo previsto, presenza e registrazioni vengono omessi. Un clic
sull'intestazione di una colonna ordina la tabella.

Esportazione: **PDF** con indicatori e tabella giornaliera.

## Chi vede che cosa

- **Il mio mese** e **Il mio anno** mostrano sempre solo le Sue registrazioni,
  anche per gli amministratori.
- **Bilancio lavoro** mostra di norma il Suo bilancio. Solo gli amministratori
  vedono una barra dei filtri con **Dipendente** e **Team** e possono così
  aprire il bilancio di un'altra persona della stessa organizzazione; **Team**
  restringe soltanto l'elenco dei dipendenti selezionabili.
- Il bilancio lavoro calcola solo il periodo scelto. Non mostra il saldo
  progressivo del Suo conto ore – per questo veda «Conto ore e approvazione
  mensile».
