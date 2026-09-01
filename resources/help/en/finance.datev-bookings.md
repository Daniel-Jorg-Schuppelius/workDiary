---
title: "DATEV posting batch"
topic: finance.datev-bookings
version: 2
audience: []
modules:
    - module.finance
schema: process
related:
    - invoices.manage
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
---

## Purpose and background

The DATEV posting batch hands issued invoices, credit notes and
optionally approved expenses of a closed period to the tax advisor as
a verifiable DATEV file (format V700). Principle: WorkDiary creates
**no** accounting, but a clean handover batch. If external billing
software (DATEV or Lexoffice) runs the invoices, they do **not**
belong in the local batch — they are excluded automatically and shown
in the review view.

## Requirements

The administration stores the organisation's accounting configuration
once:

- advisor and client number,
- chart of accounts (SKR03 or SKR04) and account number length,
- default revenue account plus a separate account for tax-free 0 %
  revenue,
- the base of the debtor number range,
- the mapping of tax rates (19 %, 7 %, 0 %) to DATEV posting keys,
- the write-protection flag (GoBD) and character set (usually
  ISO-8859-1).

A debtor number can be kept per customer; if missing, it is derived
deterministically from the number range base and the customer number.
Creating, finalising and downloading batches is for the
**accounting** role (and administrators); the configuration is
maintained by administrators.

## Recommended workflow

1. **Create the batch:** choose the period, optionally include
   approved expenses — a **draft** with the posting-ready documents
   appears.
2. **Review:** the preview shows the posting record per document —
   debit/credit flag, debtor and revenue account, posting key,
   document number, gross amount — plus the total. Missing master
   data appears as a **warning**, missing posting keys as a blocking
   **error**.
3. **Finalise:** only now the DATEV file is created; a SHA-256
   checksum is recorded and the documents count as handed over. A
   finalised batch is **immutable**.
4. **Download** and provide it to the tax office.

![DATEV posting batches with key figures, configuration and batch creation](media/buchhaltung/datev-stapel.png)
*The batch overview: key figures, configuration, EXTF master data and “Create batch”.*

## Practical example

At the start of the month accounting creates the batch for the
previous month: two documents warn about missing debtor numbers —
after maintaining them on the customer the warnings disappear, the
batch is finalised and the CSV goes to the tax office with its
checksum.

## Common mistakes

- **Handing over the same invoice twice:** finalised documents are
  locked — corrections run via credit note or correction document in
  the next batch.
- **Ignoring warnings:** missing master data otherwise surfaces at
  the tax office.
- **Expecting receipts in the batch:** PDFs/photos are not part of
  the batch; they stay on the case and go to the office separately.

## Effects and next steps

Issued and paid invoices with a document date in the period are
considered; credit notes become reversed postings. After the
handover: maintain payment reconciliation and export the next period
only after it has been closed.
