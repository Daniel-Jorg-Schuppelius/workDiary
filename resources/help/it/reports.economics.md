---
title: "Redditività"
topic: reports.economics
version: 3
keywords:
    - consuntivo
    - margine di contribuzione
    - margine
    - redditività dei progetti
    - confronto preventivo consuntivo
    - confronto con il budget
    - costo orario interno
    - progetti in perdita
    - controllo di gestione
    - top e flop
    - profittabilità
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

La pagina **Redditività** (consuntivo) in **Report** → **Finanze e
audit** → **Redditività** mostra il margine di contribuzione per cliente
(**Redditività per cliente**) e per progetto (**Redditività &
previsto-vs-effettivo per progetto**) nel **Periodo** scelto:

- **Ricavo** = tempi fatturabili × tariffa + materiale fatturato + spese
  fatturabili. La fattura determinante è gestita dal sistema di
  fatturazione esterno; qui gli importi registrati servono da proiezione.
- **Costi** = tariffa di costo interna del tempo × tempo + spese dirette
  per materiale e giustificativi.
- **Margine di contribuzione** = ricavo − costi, indicato anche come
  **Margine** in percentuale.

Ulteriori analisi:

- **Classifica**: «Top 5 clienti (margine di contribuzione)», «Flop 5
  clienti (margine di contribuzione)» e lo stesso per i progetti – così
  diventano visibili clienti e progetti in perdita.
- **Tempo non fatturabile**: per cliente, **Fatturabile (min.)**, **Non
  fatturabile (min.)** e **Quota %** mostrano quanto tempo è stato
  registrato senza fatturazione – un indizio di rilavorazioni e
  concessioni. Per progetto, **Rilavorazione (min.)**, **Gesto commerciale (min.)** e **Rilavorazione %** riportano i tempi registrati con un
  motivo di rilavorazione o di concessione.
- **Previsto vs effettivo** per progetto: **Effettivo (min.)** rispetto a
  **Piano (min.)** dal budget di tempo del progetto (**Δ Min.**) e costi
  effettivi rispetto al **Budget del piano** in euro (**Δ Budget**).

Note sulla qualità dei dati:

- Se per una parte dei tempi **non è impostata una tariffa di costo
  interna**, questi confluiscono con 0 € di costi – il margine di
  contribuzione risulta quindi troppo ottimistico. I costi riportano
  allora un asterisco con l’avviso «Tariffe di costo non completamente
  compilate».
- I progetti **senza budget di tempo/budget** mostrano «–» nelle colonne
  del piano.

Esportazione in **PDF**, **CSV** o **Excel** per direzione e controllo di
gestione. La pagina mostra dati finanziari dell’intera organizzazione ed
è disponibile solo per le persone con il permesso **Visualizza i
report**.
