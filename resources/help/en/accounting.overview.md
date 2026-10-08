---
title: "Local accounting"
topic: accounting.overview
version: 3
keywords:
    - general ledger
    - bookkeeping
    - financial accounting
    - set up accounting
    - double-entry bookkeeping
    - cash basis accounting
    - income surplus calculation
    - accounting start date
    - replace accounting software
    - built-in accounting
    - chart of accounts
    - SKR03
    - SKR04
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
schema: process
related:
    - accounting.posting
    - accounting.closing
    - finance.datev-bookings
---

## Purpose and background

Local accounting runs its own general ledger inside WorkDiary — for
organisations without separate accounting software. It replaces
neither the accounting plugins nor their data sovereignty. Three
questions stay strictly separated: **invoicing sovereignty** (who
issues invoices?), **master data sovereignty** (who keeps customers
and suppliers?) and **posting sovereignty** (who keeps the ledger?) —
per period either WorkDiary leads or exactly one external system.

## Requirements

- The **accounting** role or administration.
- A decision for a profile: cash-basis accounting (EÜR) or
  double-entry bookkeeping.
- Base currency, financial year and posting start (cut-off date).
- No external system with posting sovereignty in the same period.

## Recommended workflow

1. Open **Sales & billing** → **Accounting** → **Setup** and choose the
   profile.
2. Set base currency, financial year and posting start.
3. Work through the **preflight**: it checks whether the organisation
   can post completely on its own from the cut-off date.
4. Only when no item is red any more, **activate** local accounting.
5. From then on, postings run through the journal (see "Posting"),
   the closing through the closing page.

![Local accounting setup with profile choice and preflight](media/buchhaltung/buchhaltung-einrichtung.png)
*The setup: accounting profile on the left, the preflight on the right — activation only without red items.*

## Practical example

A small craft business cancels its accounting software at year's end:
in December the EÜR profile is set up, the preflight worked through
and the posting start set to 1 January. December documents stay in
the old system — from January WorkDiary posts.

## Common mistakes

- **Wanting to post retroactively:** documents before the cut-off
  stay history and are not re-posted.
- **Double posting sovereignty:** posting in the old system and in
  WorkDiary in parallel creates two truths — the preflight prevents
  this deliberately.
- **Forcing activation despite red preflight items** — the gaps catch
  up with you at the first closing.

## Effects and next steps

With activation WorkDiary becomes the leading ledger from the cut-off
date: journal, open items and closing build on it. Next: get to know
the posting logic and document intake ("Posting") and plan the first
monthly closing.

## Chart of accounts

Maintain the accounts of local accounting under **Sales & billing** →
**Accounting** → **Chart of accounts**. The menu item appears as soon as your
organization keeps, or has kept, local accounting.

- **Chart of accounts from template:** choose an extract of SKR03 or SKR04
  under **Template** and click **Apply template**. Accounts, tax codes and
  matching posting rules are created so that the posting inbox works right
  away; existing accounts and rules stay unchanged. The template is a starting
  point for Germany – account choice and tax mapping need professional review
  before the first posting.
- **Add account** and **Edit account:** **Account** (the account number,
  unique per organization), **Name**, **Account type**, **Balance side**
  (prefilled from the account type), **DATEV account** (export only), the flags
  **Open items**, **Bank**, **Cash**, **Clearing** and **Cost centre
  required**, for cash-basis accounting the **Cash-basis line** and
  **Deductible share (%)**, and a **Description**. Entries on accounts with the
  **Open items** flag appear in the list of open items.
- **Deactivate** instead of delete: a deactivated account keeps its entries
  but can no longer be selected for new ones. By default the list shows
  **active only** accounts; the search (number, name) and the account type
  filter narrow it further.
- **Import chart of accounts:** a CSV file with a header row and the columns
  `number`, `name` and `type`, optionally `normal_balance`, `is_open_item`,
  `datev_account`, `euer_category` and `deductible_percent`. Existing account
  numbers are updated, new accounts are created, nothing is deleted; faulty
  lines are skipped and counted.
- **Tax codes:** once tax codes exist, the page lists them with their field
  numbers of the German VAT return. Via **Edit** you assign one field each to
  **Tax base** and **Tax amount** – a reconciliation aid, not the form.

**Permission:** viewing with **View accounting**; template, import and all
changes to accounts and tax codes with **Set up accounting**.
