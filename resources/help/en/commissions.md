---
title: "Commissions"
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

## Tiers, cap, liability period, partial payments and agents

A rule can carry **tiers**: once a person's revenue in the month, quarter or
year reaches a threshold, the rate of the highest tier reached applies to the
new amount. Rows already created are not recalculated. An **annual cap** limits
the commission per calendar year; anything above it lapses with a note on the
row.

With a **liability period**, a commission becomes payable only after the days
have passed and falls into the run of that period — if the invoice is cancelled
before, it disappears before it was ever reported. If **“Accrue on partial
payments”** is set, the commission arises pro rata with each payment received
instead of only on full payment.

If a payment is reverted in the bank reconciliation, WorkDiary reverses the
commission down to the share then paid (without “Accrue on partial payments”
entirely) — as a separate negative row; the earlier row stays. If the payment
arrives again, the commission arises again.

Commissions can also go to **external agents** without a user account. They
are maintained under “Agents”, assigned on the invoice and appear in the run
and in the export next to the employees.
