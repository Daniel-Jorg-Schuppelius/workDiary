---
title: "DATEV booking batch"
topic: finance.datev-bookings
version: 1
audience: []
related:
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
    - glossary.core
---

The **DATEV booking batch** hands over issued invoices, credit notes and –
optionally – approved expenses of a closed period as a verifiable DATEV file
(format V700) to the tax advisor or accounting team.

Core principle: WorkDiary does **not** keep the books; it produces a clean
hand-over batch. When an external invoicing program (DATEV or Lexoffice) leads
the invoicing, those invoices do **not** belong in the local booking batch –
they are excluded automatically and reported in the review view.

## Preparation

Before the first export the administration stores the organisation's
**accounting configuration**:

- advisor and client number,
- chart of accounts (SKR03 or SKR04) and account length,
- a default revenue account plus a separate account for tax-free / 0 %
  turnover,
- the base of the debtor number range,
- the mapping of tax rates (19 %, 7 %, 0 %) to the DATEV posting keys,
- the lock flag (GoBD) and the character set (commonly ISO-8859-1).

A **debtor number** can be maintained per customer. If it is missing, it is
derived deterministically from the configured number-range base and the
customer number.

## Workflow

1. **Create a batch:** Pick the period (and optionally include approved
   expenses). A **draft** is created from the bookable documents.
2. **Review:** The preview shows the posting for each document – debit/credit
   indicator, debtor and revenue account, posting key, document number and
   gross amount – together with the total. Missing master data or posting keys
   appear as a **warning** or a blocking **error**.
3. **Finalise:** Only finalisation produces the DATEV file, records a checksum
   (SHA-256) and marks the contained documents as handed over. A finalised
   batch is **immutable**; the same invoice cannot be handed over twice.
4. **Download:** The generated CSV file can be downloaded for the firm.

## Notes

- Issued and paid invoices with a document date in the period are considered;
  credit notes are posted as a reversed entry.
- Document attachments (PDFs/photos) are not part of the batch in this first
  increment; they stay attached to the record and are provided to the firm
  separately.

## Permissions

- **Create, finalise and download batches:** the *Accounting* role (and
  administrators).
- **Maintain the accounting configuration and debtor numbers:**
  administrators.
