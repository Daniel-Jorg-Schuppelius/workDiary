---
title: "Closing and reports"
topic: accounting.closing
version: 4
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
    - DATEV tax check
    - automatic account
    - BU 40
    - missing VAT ID
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

**Without local bookkeeping:** If the organization keeps its books in an
accounting system, these reports still open but show a notice at the top:
they evaluate postings in WorkDiary only and therefore remain empty or
incomplete. Revenue and open items from the accounting system appear in the
billing report, the document flow and the **Open items** tile.

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

**Tax check before the DATEV export:** **Check and export** in the DATEV
handover first shows the findings of the period, each with a link to the
booking. **Errors** block the export: a revenue or expense line without a tax
code although the booking shows output or input tax; an intra-Community supply
or reverse charge in another EU country without the partner's VAT ID; a
booking on a deactivated account. **Notes** are for review: booking records on
which the export suspends the account's automatic tax with BU 40 (with count
and account), a tax rate that differs from the code of the automatic account,
and domestic reverse charge without a VAT ID. The check reads the automatic
accounts of the chart of accounts and does not change the file. An error
disappears as soon as the booking or the master data are corrected. If it is
correct in your case, a person with the permission **Release the tax check
before the DATEV export** releases it with a reason; the release is recorded
in the accounting audit chain and applies only to the errors shown.
