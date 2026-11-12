---
title: "Local accounting"
topic: accounting.overview
version: 6
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
- **Tax codes:** the page lists the tax codes with rate, tax account, validity
  and their field numbers of the German VAT return. **Add tax code** records
  **Code**, **Name**, **Direction**, **Tax rate (%)**, **Tax account**,
  **Valid from** and the field numbers for **Tax base** and **Tax amount** – a
  reconciliation aid, not the form. Via **Edit** you change name and field
  numbers at any time. Rate, direction and tax account of a code that has
  already been used in postings only change from a new effective date: the
  previous version ends the day before, existing postings stay unchanged,
  posting rules and account defaults follow the new version. **Retire**
  removes a code from selection; existing postings keep it.
  Postings from the posting inbox carry tax code and tax amount on the revenue
  or expense line. For sales without tax the inbox picks the code by the tax
  category of the document: intra-community supply (field 41), service with
  reverse charge (21), export (43), not taxable (45); “tax-free” without a
  field number appears as a case to clarify. Small businesses get no tax code.
- **Reverse charge (recipient liable):** For the direction **Output and input
  tax (reverse charge)** the tax code additionally records **Input tax
  account**, **Tax case in incoming invoices** and **Input tax field number**.
  The templates bring § 13b (1) and (2) (19 %) and the intra-Community
  acquisition (19 % and 7 %) with their accounts, plus the code of field 60
  for sales on which the domestic customer owes the tax. Existing charts from
  the template receive them with the update.
- **DATEV:** **DATEV posting key** on the code names the tax key in the DATEV
  posting batch (e.g. 9 for input tax 19 %, 94 for § 13b). **DATEV automatic
  account** on the account names the code whose tax DATEV computes itself on
  this account (automatic account such as 8400 in SKR03); the batch sets no
  key there.

**Permission:** viewing with **View accounting**; template, import and all
changes to accounts and tax codes with **Set up accounting**.
