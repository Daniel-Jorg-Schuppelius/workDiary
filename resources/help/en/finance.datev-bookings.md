---
title: "DATEV posting batch"
topic: finance.datev-bookings
version: 6
keywords:
    - DATEV export
    - tax advisor
    - export bookings
    - EXTF
    - chart of accounts
    - SKR03
    - SKR04
    - debtor number
    - posting key
    - revenue account
    - accounting export
    - journal entries
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

For local accounting the **Accounts** section also holds the **cash discount
account** and the **write-off account (bad debts)**: the counter accounts used
when an open item is settled with a cash discount or written off. Left empty,
the default of the chart of accounts applies (SKR03 8736 and 2400, SKR04 4736 and 6930). For payables there are the
**account for cash discounts received** (3736 or 5736) and the **write-off
account for payables** (other income, 2709 or 4839). If a local chart of accounts exists, an entered account must exist
there; if an effective account is missing, the page says so.

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

## Transmission and cover report

After finalising, **Record transmission** captures what happens to the batch: **transmitted** to the accountant, then **confirmed** or **rejected** there. A rejected batch is transmitted again once the issue is resolved; corrections to documents still go through the next batch. Each step is recorded with time, person and an optional note in the batch history, and the list shows the current status. Please keep notes free of personal data, as the history cannot be changed.

The **cover report (PDF)** summarises the batch for the accountant: period, advisor and client number, number of booking records and reversals, total, file checksum, totals by revenue account and by tax key, and the history.

## DATEV handover from the journal

If WorkDiary keeps the general ledger, the DATEV posting batch is built from
the posted journal entries (card **DATEV handover** in **Period closing**).
Each entry becomes booking records against an anchor line – usually the
receivable, payable or money account – so DATEV books it exactly once. A line
with a tax code goes out gross with its **DATEV posting key**; the tax lines
are omitted because DATEV books the tax itself. With reverse charge (§ 13b,
intra-Community acquisition) the amount stays net and DATEV books output and
input tax. On an automatic account (field **DATEV automatic account** on the
account) DATEV computes the tax from the account, and the batch sets no key
there. If the automatic does not fit the entry, key 40 suspends it and the tax
remains a separate record. A cash discount with tax correction also goes out as
one record.

## Sales without tax

If an invoice shows no tax, the batch books it according to its tax case — like the
accounting: intra-community supply (SKR03 8125 / SKR04 4125), service to another EU
country (8336/4336), domestic reverse charge (8337/4337), export (8120/4120),
non-taxable sale in a third country (8338/4338). These are DATEV automatic
accounts; the batch sets no tax key there. Tax-exempt domestic sales stay on the
configured account for tax-exempt revenue.
