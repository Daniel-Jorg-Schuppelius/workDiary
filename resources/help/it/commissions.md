---
title: "Provvigioni"
topic: commissions
version: 1
audience:
    - admin
    - geschaeftsfuehrung
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

## Scaglioni, tetto, periodo di garanzia, pagamenti parziali e intermediari

Una regola può avere **scaglioni**: quando il fatturato di una persona nel
mese, trimestre o anno raggiunge una soglia, al nuovo importo si applica
l'aliquota dello scaglione più alto raggiunto. Le righe già create non vengono
ricalcolate. Un **tetto annuo** limita la provvigione per anno solare; quanto
eccede decade, con un'annotazione sulla riga.

Con un **periodo di garanzia** una provvigione diventa pagabile solo dopo i
giorni indicati e rientra nella liquidazione di quel periodo — se la fattura
viene annullata prima, scompare prima di essere comunicata. Se è attivo **«Già
sui pagamenti parziali»**, la provvigione nasce pro rata a ogni incasso invece
che solo al pagamento completo.

Se un pagamento viene annullato nella riconciliazione bancaria, WorkDiary storna
la provvigione fino alla quota allora pagata (per intero senza «pagamenti
parziali»), come riga negativa a sé; la riga precedente resta. Se il pagamento
arriva di nuovo, la provvigione nasce di nuovo.

Le provvigioni possono andare anche a **intermediari esterni** senza account
utente. Si gestiscono in «Intermediari», si assegnano sulla fattura e compaiono
nella liquidazione e nell'esportazione accanto ai dipendenti.
