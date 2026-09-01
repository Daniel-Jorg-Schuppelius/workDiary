---
title: "Fixed asset register and depreciation"
topic: accounting.fixed-assets
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.closing
    - accounting.posting
    - accounting.overview
---

The **fixed asset register** is the accounting view of long-lived assets:
acquisition or production cost, useful life, residual value and the accounts
involved. It answers “what is this machine still worth on the reporting
date” — not “where is it and when was it last serviced”. That belongs in the
equipment record.

**Equipment and fixed asset are two different things.** Linking them is
possible but not required: an operating fixture can be capitalised without an
equipment record, and a piece of equipment may be low-value and written off
at once. Treating them as the same thing produces either assets without a
book value or equipment that does not exist in the books.

## What is recorded here

1. **Acquisition**: date, cost, currency. The system assigns the number.
2. **Useful life in months** and the **depreciation method**. Together they
   determine how the value is spread over the years.
3. **Residual value**, if a memo value or an expected sale proceeds remains
   at the end of the useful life.
4. **Accounts** for fixed assets and depreciation — they steer where the
   depreciation entry goes.

## How depreciation comes about

Depreciation lines are **calculated, not typed**. The period-end close
proposes them per asset and financial year; posting happens exclusively
through the posting inbox.

**The register posts nothing by itself.** That is deliberate: depreciation is
a decision taken at the year-end close, not a side effect of maintaining
master data. Creating an asset does not change any balance.

## Disposal

A disposal (sale, scrapping, theft) is recorded with a date. The asset does
**not** disappear from the register — the history stays readable, otherwise a
later reconciliation against the balance sheet would be impossible.
