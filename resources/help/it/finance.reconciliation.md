---
title: "Riconciliazione dei pagamenti"
topic: finance.reconciliation
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

La **riconciliazione dei pagamenti** importa estratti conto in formato
**CAMT.053** o **MT940**, normalizza i movimenti bancari in un'area di
verifica e propone fatture aperte o spese approvate da abbinare. L'import da
solo non modifica alcun documento: solo la **conferma** imposta la fattura su
"pagata" o la spesa su "rimborsata". Nel dettaglio dell'estratto ogni
movimento mostra lo stato e proposte di abbinamento con punteggio e
motivazione; in alternativa puoi metterlo da parte o segnarlo come non
abbinabile. Una conferma è reversibile, il movimento bancario stesso non
viene mai modificato. Sconto cassa e differenze di arrotondamento sono
tollerati; i dati bancari personali sono cifrati e ogni azione è
protocollata in una catena di hash. Import e conferme richiedono il ruolo
*Contabilità*.
