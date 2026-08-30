---
title: "Commissions"
topic: commissions
version: 1
audience:
    - admin
    - buchhaltung
related:
    - invoices.manage
    - finance.reconciliation
---

Commissions arise from **paid** invoices. The pages show three things: the
**rules** (who gets what, and how much), the **open lines**, and the **runs**
used to settle them.

## The single moment a commission arises

Exactly when an invoice switches to **paid** — no matter how that happens
(bank reconciliation, cash book, retainer settlement, manual action).
**Issued-but-open never creates a commission.**

That is not a detail: whoever pays commission on invoicing pays for revenue
that may never arrive — and has to claw it back later.

## Cancellation and credit note: reversal, not correction

A cancelled or credited invoice **does not change the original commission
line**. Instead a second line with negative amounts is created. Two cases:

- The original line is **not yet settled**: both lines move to “reversed” and
  end up in no run — nothing was ever reported. The record remains as a paper
  trail.
- The original line sits in a **closed run**: it stays unchanged, because the
  run is the document of record towards payroll. The negative line falls into
  the next run.

The reason for this awkwardness: a closed run has already been reported and
possibly paid out. Changing it after the fact would mean falsifying a record
that someone else has already processed.

## Runs

A run bundles the open lines of a period. Once closed it is the document of
record — corrections go through the next run, never by editing the old one.
