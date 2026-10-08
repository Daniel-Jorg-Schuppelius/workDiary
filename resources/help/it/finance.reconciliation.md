---
title: "Riconciliazione dei pagamenti"
topic: finance.reconciliation
version: 3
keywords:
    - riconciliazione bancaria
    - importare estratto conto
    - abbinamento pagamenti
    - incassi
    - fattura pagata
    - movimenti bancari
    - CAMT
    - MT940
    - sconto cassa
    - pagamento parziale
    - riferimento RF
    - quadratura
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

La **Riconciliazione dei pagamenti** importa gli estratti conto bancari in
formato **CAMT.053** (preferito) o **MT940** (ripiego), normalizza i
movimenti bancari in un'**area di verifica** e propone per l'assegnazione
fatture aperte o note spese approvate. **La sola importazione non modifica
alcun documento** – solo la **conferma** imposta `Fattura → pagata` (con
data di pagamento) oppure contrassegna una nota spese come rimborsata.

## Procedura

1. **Importare:** caricare il file bancario (facoltativamente scegliere un
   proprio conto bancario; altrimenti viene assegnato automaticamente
   tramite l'IBAN). I file identici vengono respinti come duplicati in base
   all'hash del file; i movimenti già noti vengono saltati in caso di nuova
   importazione.
2. **Verificare:** nel dettaglio dell'estratto ogni movimento mostra uno
   stato (Aperto/Assegnato/Accantonato/Non assegnabile) e – se aperto –
   **Proposte di assegnazione** con punteggio di corrispondenza e
   motivazione (numero di fattura, importo, sconto cassa, corrispondenza
   IBAN, vicinanza della data).
3. **Confermare:** con *Conferma* l'assegnazione viene creata e il suo
   effetto viene applicato al documento. In alternativa *Accantona* (ad es.
   commissione bancaria) oppure *Non assegnabile*.
4. **Annullare:** un'assegnazione confermata è **reversibile** – viene
   annullata e l'effetto sul documento (pagata/rimborsata) viene revocato
   solo se questo movimento costituiva il pagamento. **Il movimento
   bancario stesso non viene mai modificato.**

## Casi pratici

- **Sconto cassa:** un pagamento inferiore entro la tolleranza di sconto
  (standard 3 %) vale come pagamento completo.
- **Tolleranza di centesimi:** differenze di arrotondamento fino a 2
  centesimi non impediscono una proposta.
- **Pagamento parziale/in eccesso:** vengono gestiti come tipo di
  assegnazione a sé; in caso di pagamento parziale la fattura resta
  aperta.
- **Catena dei saldi:** saldo iniziale + somma dei movimenti viene
  confrontato con il saldo finale; le differenze vengono segnalate come
  avviso.
- **Valuta estera:** i movimenti in una valuta diversa vengono soltanto
  riconosciuti e contrassegnati per un chiarimento manuale.

## Protezione dei dati

I dati bancari riferiti a persone (nome, IBAN, causale della controparte)
sono conservati in forma **cifrata**. L'abbinamento avviene esclusivamente
tramite derivati non cifrati (hash dell'IBAN, numeri di fattura estratti,
importi, date). Ogni azione di assegnazione viene registrata a prova di
revisione in una catena di hash.

## Autorizzazioni

- **Importa file bancario** e **confermare/annullare assegnazioni:**
  ruolo *Contabilità* (oltre all'amministrazione).
- **Gestire i propri conti bancari:** solo l'amministrazione.

## Riferimento di pagamento RF

Se nelle impostazioni dell'organizzazione è attivato il riferimento di
pagamento RF, ogni fattura riporta un riferimento creditore RF (ISO 11649)
ricavato dal suo numero — nelle indicazioni di pagamento e nel GiroCode
come riferimento strutturato. Se il cliente effettua il bonifico con
questo riferimento, la riconciliazione dei pagamenti riconosce la fattura
grazie a esso, anche se scritto in gruppi di quattro.
