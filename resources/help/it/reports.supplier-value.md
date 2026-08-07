---
title: "Valore fornitore"
topic: reports.supplier-value
version: 1
audience: []
related:
    - reports.supplier-analysis
    - reports.customer-value
---

Il report sul valore fornitore è la controparte di acquisto del valore
cliente e risponde: **da quali fornitori dipendiamo, dove si trova il
rischio di concentrazione, quali sono strategici, inattivi o
occasionali?**

## Come leggere questo report

- **Spesa per fornitore (Pareto)**: barre decrescenti più una linea di
  percentuale cumulata — la rapidità con cui la linea raggiunge l'80 %
  mostra la dipendenza da pochi fornitori.
- **Spesa per inattività**: più a destra, più è lontano l'ultimo
  documento; i punti a destra **sopra** la linea P80 sono fornitori a
  spesa elevata che non consegnano da tempo.
- **Fornitori per segmento**: fare clic su una barra filtra l'elenco dei
  fornitori qui sotto esattamente su quei fornitori.
- **Elenco rischi**: fornitori la cui quota di spesa supera la soglia
  configurata (rischio di concentrazione mono-fonte), con andamento della
  spesa a 12 mesi (sparkline).

## R, F e M — come nascono i punteggi

Ogni fornitore attivo nel periodo riceve tre **punteggi per quintile da 1
a 5**:

- **R (Recency)** — giorni dall'ultimo documento. Più breve, più alto.
- **F (Frequency)** — numero di giorni con documento nel periodo.
- **M (Monetary)** — spesa nel periodo (documenti di acquisto dallo
  specchio, le note di credito la riducono).

Quintile significa che i fornitori vengono divisi in cinque gruppi di pari
dimensione per indicatore. I punteggi sono quindi **relativi al proprio
parco fornitori**, non assoluti.

## Segmenti

- **Strategico** — R ≥ 4, F ≥ 4, M ≥ 4 (spesa elevata, regolare,
  recente).
- **Fornitore chiave inattivo** — R ≤ 2 con M ≥ 4 (spesa elevata ma nessun
  documento da tempo).
- **Fornitore abituale** — F ≥ 3 (approvvigionamento regolare).
- **Occasionale** — tutti gli altri fornitori attivi.
- **Nuovo** — il primo documento cade nel periodo.
- **Inattivo** — nessun documento nel periodo.

Il report mostra dati finanziari ed è visibile solo agli utenti con il
permesso di reporting.
