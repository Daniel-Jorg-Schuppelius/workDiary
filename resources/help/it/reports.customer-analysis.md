---
title: "Analisi clienti"
topic: reports.customer-analysis
version: 2
keywords:
    - statistiche clienti
    - report clienti
    - ordini per cliente
    - tempo per cliente
    - tasso di rilavorazione
    - tempo non fatturabile
    - rischio di margine
    - KPI clienti
    - clienti e progetti
    - ore per progetto
    - ricavo per cliente
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.drilldown
    - reports.entry-type-analysis
    - reports.billing
---

L'**analisi dei clienti** mostra per ogni cliente impegno, rilavorazione
e tempo non fatturabile nel periodo dell'intestazione della pagina;
richiede il permesso «Visualizza i report». **Clienti e progetti**
suddivide ore e ricavo per cliente e progetto.

## Analisi dei clienti

Colonne della tabella:

- **Ordini** del periodo, **Totale** (minuti registrati),
  **Fatturabile**, **Non fatturabile** e la relativa **Quota %**: una
  quota alta è un indicatore precoce di rischi di margine.
- **Rilavorazione**: ordini con un protocollo di difetto nel periodo.
- **Punti aperti** sul cliente, sui suoi ordini e progetti, di cui
  **Escalato** (bloccati).
- **Media min./ordine** e **Tendenza 30 g**: ordini degli ultimi 30
  giorni meno quelli dei 30 giorni precedenti.

Filtri: **Progetto**, **Dipendente** e **Tipo di ordine**, inoltre
**Impegno minimo (minuti)**, **Nascondi i clienti senza valori** e
**Includi i clienti nascosti** (clienti contrassegnati con «Nascondi
nelle analisi»).

Destinazioni dei clic: il nome del cliente apre la scheda cliente,
**Ordini** e **Totale** il registro ordini del periodo. **Rilavorazione**,
**Punti aperti** ed **Escalato** aprono il drilldown con gli stessi
filtri. Sopra si trovano **Ore per cliente (top 20)** (un clic apre
Clienti e progetti per quel cliente), l'andamento degli ordini in
entrata, **Punti aperti per cliente (top 15)** e gli elenchi **Top 5
impegno**, **Top 5 rilavorazione** e **Top 5 non fatturabile**.
Esportazione in PDF, CSV o Excel.

## Clienti e progetti

La tabella **Cliente / progetto** indica il totale per cliente e, sotto,
i progetti con **Ore** e **Ricavo**, ordinati per ore in modo
decrescente; **Σ ore** e **Σ €** stanno sopra. Il ricavo è la somma
degli importi salvati delle registrazioni di tempo. I grafici mostrano
**Ore per cliente (top 20)** (un clic filtra la pagina su quel cliente)
e **Progetti principali per ore**.

Filtri: **Cliente**, **Progetto** e **Includi i clienti nascosti**. Chi
può vedere tutte le registrazioni di tempo sceglie in **Area** tra
**Solo i miei** e **Tutto il team** e filtra per **Dipendente**; gli
altri vedono i propri tempi. Esportazione in PDF, CSV o Excel.
