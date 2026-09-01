---
title: "Provvigioni"
topic: commissions
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.reconciliation
---

Le provvigioni nascono da fatture **pagate**. Le pagine mostrano tre cose: le
**regole** (chi riceve che cosa e quanto), le **righe aperte** e le
**liquidazioni**.

## L’unico momento in cui nasce una provvigione

Esattamente quando una fattura passa a **pagata** — indipendentemente dalla
via (riconciliazione bancaria, prima nota di cassa, conguaglio del forfait,
azione manuale). **Emessa ma non pagata non genera mai una provvigione.**

Non è un dettaglio: chi provvigiona all’emissione paga per un fatturato che
forse non arriverà mai — e deve poi recuperarlo.

## Storno e nota di credito: riaccredito, non correzione

Una fattura stornata o accreditata **non modifica la riga di provvigione
originaria**. Viene creata una seconda riga con importi negativi. Due casi:

- La riga originaria **non è ancora liquidata**: entrambe passano a
  «riaccreditata» e non entrano in alcuna liquidazione — nulla era stato
  comunicato. L’operazione resta come traccia documentale.
- La riga originaria si trova in una **liquidazione chiusa**: resta invariata,
  perché la liquidazione fa fede verso il libro paga. La riga negativa entra
  nella liquidazione successiva.

Il motivo di questa macchinosità: una liquidazione chiusa è già stata
comunicata ed eventualmente pagata. Modificarla a posteriori significherebbe
falsificare un documento che qualcun altro ha già elaborato.

## Liquidazioni

Una liquidazione raccoglie le righe aperte di un periodo. Una volta chiusa fa
fede — le correzioni passano dalla liquidazione successiva, mai dalla
rilavorazione della vecchia.
