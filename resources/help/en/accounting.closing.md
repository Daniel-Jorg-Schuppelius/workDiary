---
title: "Closing and reports"
topic: accounting.closing
version: 2
keywords:
    - month-end close
    - year-end closing
    - lock period
    - reopen period
    - management accounts
    - profit and loss
    - cash flow forecast
    - budget vs actual
    - VAT report
    - tax audit export
    - cost allocation
    - DATEV export
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
related:
    - accounting.overview
    - accounting.posting
---

**Periods** close in two steps: *provisional* is a signal — content complete,
correction still possible; *definitive* is a lock — the period accepts no
further entries. A preflight checks open drafts and unbalanced entries first.

**Reopening** requires its own permission, a reason, and is recorded in the
audit chain.

**Reports** read posted entries only — a draft is an intention, not a figure.
VAT and cash-basis reports are auditable **previews**: the MVP files nothing
with the tax authority.

**Exporting reports:** PDF, CSV and Excel of the financial reports, the
management report and the budget require the permission **Export reports** in
addition to the read permission; without it, the export buttons are missing.
Administrators can always export.

**Handover**: the GoBD audit package contains chart of accounts, journal, entry
lines, open items and periods; the DATEV handover is generated from posted
entries, not derived from the documents again.

**Management report, budget and liquidity:** The same posted entries feed the
**management report** (revenue, cost and result by group), the **budget
comparison** per account and cost centre — previous-year figures can be carried
over as a starting point — and the **liquidity forecast**. All three are
evaluations, not a second set of books: what you see there is what stands in
the journal. Corrections are therefore always made to the entry, never to the
report.

**Allocations, budget release and plan items:** Allocation keys distribute
the expenses of a service cost centre proportionally to other cost centres; the
management report can show this "after allocation", while the entries stay
unchanged. A released budget is locked against changes until a supplement with
a reason reopens it; if an entry exceeds the monthly budget, the entry shows a
notice but is still posted. In the liquidity forecast you add **plan items**
(such as tax prepayments or loan instalments); every Monday the app records the
weekly forecast, and "plan vs. actual" later compares it with the actual account
movements.
