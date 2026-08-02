---
title: "Valore cliente"
topic: reports.customer-value
version: 2
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

Il report sul valore cliente risponde: **di quali clienti vive l'azienda,
dove c'è rischio di concentrazione, quali clienti A sono a rischio?**

## Come leggere questo report

- **Ricavi per cliente (Pareto)**: colonne decrescenti + linea cumulata —
  quanto più in fretta la linea raggiunge l'80 %, tanto maggiore è la
  dipendenza da pochi clienti.
- **Ricavi per inattività**: più a destra = più tempo senza prestazioni;
  i punti a destra **sopra** la linea P80 sono i clienti A a rischio.
- **Clienti per segmento**: un clic su una barra filtra l'elenco clienti
  in basso esattamente su quei clienti.
- **Lista di rischio**: clienti ad alto fatturato senza prestazioni dalla
  soglia impostata, con andamento ricavi su 12 mesi (sparkline).

## R, F e M — come nascono i punteggi

Ogni cliente attivo nel periodo riceve tre **punteggi per quintili da
1 a 5**:

- **R (recency)** — giorni dall'ultima prestazione. Più recente = più alto.
- **F (frequency)** — giorni di attività nel periodo.
- **M (monetary)** — ricavi nel periodo (tempi fatturabili, stessa fonte
  del report di redditività).

Quintile significa: i clienti sono divisi in cinque gruppi uguali per
indicatore. Esempio con cinque clienti per ricavi
10.000/8.000/5.000/1.000/300 € → punteggi M 5/4/3/2/1. I punteggi sono
**relativi al proprio portafoglio**, non assoluti.

## I segmenti (vince la prima regola applicabile)

| Segmento | Regola |
| --- | --- |
| Inattivi | nessuna prestazione nel periodo |
| Nuovi | prima prestazione nel periodo |
| Champions | R ≥ 4 e F ≥ 4 e M ≥ 4 |
| A rischio | R ≤ 2 con M ≥ 4 (alto fatturato, lungo silenzio) |
| Inattivi | R ≤ 2 (attivi all'inizio, poi silenzio) |
| Fedeli | F ≥ 3 |
| Da sviluppare | tutti gli altri clienti attivi |

## HHI — concentrazione con esempio numerico

HHI = somma delle quote di ricavo **al quadrato** (in %). Due clienti al
50 % ciascuno → 50² + 50² = **5000** (estremamente concentrato); dieci
clienti al 10 % → 10 × 10² = **1000** (non critico). Riferimenti: sotto
1500 non critico, 1500–2500 moderato, sopra 2500 rischio elevato.

## Cosa fare con i segmenti

- **Champions**: fidelizzare — servizio prioritario, niente esperimenti.
- **A rischio**: contattare attivamente, capire il silenzio.
- **Da sviluppare**: offerte mirate — qui c'è il potenziale di crescita.
- **Nuovi**: completare bene l'onboarding, assicurare il secondo ordine.
- **Inattivi**: decidere consapevolmente — riattivare o chiudere.
- **HHI/top 5 alto**: dare priorità all'acquisizione di nuovi clienti.

Ogni punto del grafico e ogni riga di tabella porta con un clic alla
base dati (report clienti & progetti o elenco filtrato).
