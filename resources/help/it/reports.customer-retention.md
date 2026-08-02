---
title: "Fidelizzazione clienti"
topic: reports.customer-retention
version: 2
audience: []
related:
    - reports.customer-value
    - reports.customer-analysis
---

Il report mostra **quanto bene l'azienda mantiene i propri clienti** — e
da cosa si alimenta la base clienti.

## Leggere la matrice delle coorti

I clienti sono raggruppati per **anno della prima prestazione** (a livello
di organizzazione, indipendentemente dal filtro periodo). Ogni riga è una
coorte, ogni colonna «+n» l'n-esimo anno successivo. Esempio: riga
**2028 (n=12)**, colonna **+2** = 75 % → dei 12 clienti arrivati nel 2028,
9 hanno acquistato prestazioni anche nel 2030. Se una riga crolla presto,
i clienti si perdono subito dopo l'ingresso. **Un clic su riga o cella**
apre l'elenco nominativo della coorte.

## Ponte della base clienti — definizioni

«**Attivo**» a una data significa: prestazione entro la soglia impostata
prima di quella data (365 giorni di default, filtro «Perso dopo»). Il
ponte torna esattamente:

Base a inizio **+ nuovi clienti** (prima prestazione nel periodo)
**+ riconquistati** (prima inattivi, di nuovo attivi)
**− nuovi tornati inattivi** (primi ordini senza seguito)
**− persi** (attivi all'inizio, non alla fine)
= base a fine periodo.

Un clic su un passo del ponte salta all'elenco nominativo sottostante;
ogni nome porta al report clienti & progetti.

## Indicatori

- **Tasso di ritorno**: quota dei clienti attivi l'anno scorso che sono
  attivi anche nell'anno di riferimento — l'indicatore più onesto.
- **Età media cliente**: anni dalla prima prestazione, mediati sui
  clienti attivi alla fine.

## Cosa farne

- La coorte crolla nell'anno +1 → rivedere onboarding / secondo ordine.
- I clienti persi si accumulano → raccogliere le cause (prezzo, qualità,
  referente), avviare un recupero mirato.
- Tasso di ritorno sotto ~70 % in un business ricorrente → attivare
  misure di fidelizzazione (contratti di manutenzione, appuntamenti
  periodici).
