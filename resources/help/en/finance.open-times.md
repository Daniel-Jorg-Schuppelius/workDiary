---
title: "Open times"
topic: finance.open-times
version: 2
audience: []
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

The **Open times** worklist shows all time entries of the
organization that have **not been billed** yet — regardless of who
recorded them. It is the accounting team's control instrument so no
times slip through before an invoicing run.

What counts as "open"? An entry that has not been consumed by any
billing path yet — neither by a local invoice nor by a customer
account closing or a facturation handover.

Customers with a running balance (special terms in "customer account"
or "retainer" mode) are **not** listed: their times are not invoiced
but settled through the monthly block on the customer file — here they
would be permanent residents. A note above the list states how many
entries are hidden this way. Customers in "monthly invoice" mode stay
visible, they run through normal invoicing.

Features:

1. **KPIs** at the top: number of open entries, open time (clock and
   decimal format), projected net revenue. The warning tiles "Late
   entries" and "Older than 45 days" always count across the whole
   backlog — regardless of the selected period.
2. **Period**: the list follows the global date selection in the
   page header. From/to parameters in the address bar (bookmarks)
   override it.
3. **Filters**: customer, project, employee, and the billable
   toggle. Use "Non-billable only" to review times marked
   non-billable deliberately or by mistake.
4. **Totals per customer & project** as an expandable block above
   the entry list.
5. **CSV export** with the duration in both formats (H:MM and
   decimal).
6. **Mark as billed**: for rolling out the system, closes all open
   times up to a cut-off date that were already billed outside the
   system — optionally for a single customer and, if desired,
   including non-billable entries. The action is available to
   administration and accounting and cannot be undone with a click.

The page is visible to roles with the permission "view all time
entries" (by default accounting, management, and administration).
