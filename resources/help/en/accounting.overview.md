---
title: "Local accounting"
topic: accounting.overview
version: 2
audience:
    - admin
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

1. Open **Finance → Set up accounting** and choose the profile.
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
