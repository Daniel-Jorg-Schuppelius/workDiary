---
title: "Workbook and reconciliation board"
topic: finance.resale.board
version: 1
keywords:
    - workbook
    - reconciliation board
    - timeline
    - fit
    - takeover
    - bad debt
    - insolvency
    - order change
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.reconcile
    - finance.resale.subscription
---

The **workbook** of an invoice recipient is where assignments are made: their
subscriptions at the top, below that per product the reconciliation board
with periods and invoice lines on one timeline. The tiles above the board
filter it to the affected products.

**Workbook and reconciliation board:** The reconciliation of a recipient is
the workbook: at the top the subscriptions with edit, takeover, transfer
licences and return, below that the reconciliation board per product. On the
left all periods in date order with target and coverage, on the right the
invoice lines: linked ones with their service period, for open periods the
fitting free ones, invoices to other customers and voided ones. Lines
without a fitting period appear as their own row “without period” at their
date. Each free invoice sits only at the period it fits best, and “Assign …”
assigns it with one click; the amount is what the period still needs. An
offset of a few weeks between the provider’s order date and the invoice date
is normal and not shown; a collective invoice that covers the whole period
fits. Invoices without a fitting period appear as a row “without period”
with “Create subscription” or “Assign to another period”. Doubtful
assignments ask before saving; in the link dialog and in the quick
assignment on the subscription page you confirm them with the checkbox.
Links that do not fit in time are marked yellow — check them and unlink them
if needed. “Work on the next recipient” in the overview leads through all
recipients with an open amount; a recipient is done when no due period is
open and no template finding remains. If the provider renews a subscription
as a new order, the proposal run waives the short remaining period of the
old order and moves its links to the first period of the new one. The remaining period
then shows as “Order change”, not as a waiver: the successor’s invoices cover
the time.

**Fit:** Every invoice line carries a fit to the period — from the distance
between the period start and the nearest cycle start of its service period,
or to the invoice date without a service period. Up to 14 days counts as
exact, up to 90 days as shifted; lines that only overlap, lie far away or
outside, or fit another open period of the same product clearly better ask
before being assigned: in the workbook as a confirmation at the button, in the
link dialog and in the quick assignment of the subscription page as a
checkbox. If the service period covers the whole period, the line fits
without a query.

**Insolvency and takeover:** If an invoice was voided because the customer
is insolvent, record the period on the board with “Record as bad debt” — the
waiver dialog has the reason with the invoice number prefilled. If another
company continues the licences, use “Takeover” on the subscription: new
holder and effective date. If the effective date is at the start of the
first open period, the new holder is also billed for the open periods; if it
is later, the register optionally waives the open periods before it as bad
debt. An effective date at the subscription start moves the whole
subscription to the new holder, otherwise an assignment starts on the
effective date. If an invoice already goes to the successor company, the
board offers the takeover directly at that invoice.
If periods are already invoiced to the previous holder, the register
rejects an effective date at the subscription start — it must then be from
the first open period.
