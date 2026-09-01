---
title: "Cash book"
topic: finance.cashbook
version: 1
audience:
    - admin
modules:
    - module.kasse
related:
    - invoices.manage
---

The **cash book** documents cash income and expenses in a GoBD-compliant way
(MVP-414). workDiary is not a point-of-sale system (no POS, no TSE duty).

- **Immutable**: entries get a sequential voucher number and a hash chain;
  editing and deleting are technically impossible.
- **Reversal instead of deletion**: corrections are counter-entries with a
  mandatory reason; the original remains.
- **Daily closing**: cash count with expected/counted/difference; afterwards
  all entries up to the closing date are locked.
- **Cash payment**: an income entry can reference an invoice — full coverage
  marks the invoice as paid.
- The cash book is part of the **GoBD Z3 export** (kassenbuch.csv).
