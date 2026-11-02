---
title: "Billing periods"
topic: finance.resale.periods
version: 1
keywords:
    - periods
    - compute proposals
    - invoice draft
    - bulk actions
    - waive
    - disputed
    - back-billing
    - prepayment
    - cut-off date
    - recurring run
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.plan
    - finance.resale.board
---

The **periods page** lists the due billing periods of all subscriptions: here
you decide per period (confirm, assign, waive, dispute), start the proposal
run, create invoice drafts and open the template proposal. Status tiles and
the filter "Problems only" narrow the list.

**Periods:** The period page shows the due periods of all subscriptions with
status tiles (open, billed, partial, waived, disputed). "Compute proposals"
matches the open periods against the licence lines of the mirrored invoices
and creates proposals; you confirm them, link a line by hand (only invoices of
the same recipient, only free licence months) or waive with a reason
("goodwill").
Confirming needs an open proposal; waiving or disputing needs a due,
undecided period — single and bulk action check the same.
In the link dialog the invoices within the period window come first, below them
all other invoices of the recipient; the search field finds any by its number,
and a used one names where it is linked. The subscription and period lists also
search by invoice number. Decided periods are no longer touched by planning; "reopen"
opens them again. If a linked invoice is later voided in Lexoffice, the next
run sets the link to zero months, notes the cancellation and reopens the
period so the replacement invoice can be linked. Domain subscriptions are
covered by lines that name the domain (such as ".de domain incl. management
(lastkraft-logistik.de)"), regardless of the article’s product classification.
Domains a subreseller settles through the provider portal are covered by the
domain plugin’s accounting journal: one voucher per subuser and month,
numbered “customer number-MM-YYYY” (enter the customer number on the subuser’s
page); the recipient is the subuser’s customer.

**Sure and unsure proposals:** The proposal run (hourly and after every
import) confirms sure matches itself — one voucher whose invoice date lies
at most 14 days before to 45 days after the period start and which fully
covers the period with the matching licence count; the confirmation appears
in the log as “confirmed automatically”. Everything else stays a proposal
and carries “unsure” with the reason in the tooltip. Proposals are valid without
confirmation: the periods page shows “Problems only” (open, partial,
disputed) by default; unconfirmed proposals are reached as a spot check via
the filter and the state in the overview. Clicking the voucher number
opens the voucher: lines, service period, invoice text, consumption per line
and the comparison with the period (date within the window, service,
article) — so you can see before confirming whether the invoice belongs to
the period.

**Bulk actions:** On the periods page you select rows via the checkboxes
and confirm all proposals at once, waive with one reason for all (“Waive /
dispute …”) or have a draft created per invoice recipient of the selection.
Decided and upcoming periods are skipped and counted; every period gets the
same log entry as with the single action. There is no preselection.

**Invoice draft:** From all open periods of an invoice recipient a draft is
created with one click — with Lexoffice invoicing as a draft in Lexoffice
(nothing is finalised; you review and issue there), with local invoicing as
a local invoice draft with lines and proposed links. One line per
subscription and period, end customer in the description, quantity in
months for monthly articles. The periods remember the draft: a second click
does not create a second one but names the pending draft with number and
date; only when the draft became an invoice or the period is decided are
they free again; until then they show “In draft” instead of “missing” or
“Open”. The dialog lists only recipients with open periods and a
sale price and names below what is already in a draft. Creating requires
the right *Create invoice drafts from periods*.
Two simultaneous clicks (double click, second browser window) also create
only one draft — the second reports that a draft is being created right now.

**Back-billing and advance planning:** The invoice proposal bundles contiguous
open periods of a subscription into one line — always for monthly articles,
for annual subscriptions with one license ("3 year" for three open years);
partially covered periods and gaps start a new line. This way old years can
be back-billed in one draft. For prepayments the dialog has a cut-off date
(due only, +1, +2, +3 years): the planning creates the periods up to the
cut-off, and the daily run keeps periods with a draft stamp. With external
invoicing authority, the recurring run can create, monthly on the first, one
transfer per recipient to their accounting program (switch and lead time in
the "Billing" dialog); subscriptions with a running recurring template are
left out.
