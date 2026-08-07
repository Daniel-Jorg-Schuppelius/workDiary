---
title: "Analisi dei fornitori"
topic: reports.supplier-analysis
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-value
---

L'analisi dei fornitori è la controparte di acquisto dell'analisi clienti
e risponde: **per cosa spendiamo, da quali fornitori dipendiamo, dove ci
sono debiti aperti?**

## Come leggere questo report

- **Spesa per fornitore (Pareto)**: barre decrescenti più una linea di
  percentuale cumulata — la rapidità con cui la linea raggiunge l'80 %
  mostra la dipendenza da pochi fornitori (rischio di concentrazione
  negli acquisti).
- **Spesa per mese**: andamento della spesa dell'organizzazione negli
  ultimi dodici mesi, indipendentemente dal periodo selezionato.
- **Importo aperto per fornitore**: documenti di acquisto non ancora
  completamente pagati — i debiti attuali.

## Base dati

La spesa proviene dallo **specchio dei documenti della contabilità**
(fatture di acquisto, note di credito di acquisto e documenti generici
per fornitore). Le note di credito riducono la spesa. Bozze e documenti
annullati non contano. Il report funziona quindi **senza il modulo di
magazzino**.

Se il **modulo di magazzino** è attivo, si aggiungono per fornitore gli
**ordini** (emessi nel periodo) e gli **ordini aperti** (in corso).

## Indicatori

- **HHI (concentrazione)** — indice di Herfindahl-Hirschman sulla spesa:
  sotto 1500 non critico, 1500–2500 moderato, sopra 2500 alto.
- **Quota top 5** — quota dei cinque fornitori con la spesa più alta; il
  rischio di concentrazione inizia intorno al 60 %.
- **Trend %** — spesa nel periodo rispetto al periodo di confronto
  immediatamente precedente, di pari durata.

Ogni riga apre la **pagina di dettaglio del fornitore** al clic. Il
report mostra dati finanziari ed è quindi visibile solo agli utenti con
il permesso di reporting.
