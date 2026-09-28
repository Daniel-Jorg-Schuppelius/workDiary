---
title: "Riconciliazione dei pagamenti"
topic: finance.reconciliation
version: 2
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
motivazione; in alternativa può metterlo da parte o segnarlo come non
abbinabile. Una conferma è reversibile, il movimento bancario stesso non
viene mai modificato. Sconto cassa e differenze di arrotondamento sono
tollerati; i dati bancari personali sono cifrati e ogni azione è
protocollata in una catena di hash. Import e conferme richiedono il ruolo
*Contabilità*.

## Riferimento di pagamento RF

Se il riferimento di pagamento RF è attivato nelle impostazioni dell'organizzazione, ogni fattura riporta un
riferimento creditore RF (ISO 11649) ricavato dal suo numero, nei dati di
pagamento e come riferimento strutturato nel GiroCode. Se il cliente paga
con questo riferimento, la riconciliazione riconosce la fattura grazie a
esso, anche se scritto in gruppi di quattro.
