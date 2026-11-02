---
title: "Reconciliation per invoice recipient"
topic: finance.resale.reconcile
version: 1
keywords:
    - reconciliation
    - invoice recipient
    - work list
    - open amount
    - never invoiced
    - invoice to another customer
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.board
    - finance.resale.periods
---

The **reconciliation** sets, per invoice recipient, the due periods against
the license lines of their invoices. The list names per recipient the open
amount, the template findings and whether they are done; the default filter
hides finished recipients, and "Work on next recipient" opens the first open
workbook.

**Reconciliation per invoice recipient:** When periods stay open and it is
unclear whether an invoice is missing or merely the assignment, use the
reconciliation (button in the subscription list, on the periods page and
on the customer). Per recipient — the customer including their end
customers — it sets the due periods of all subscriptions against the
licence lines of their invoices, in licence months per product: *required*
from the periods, *invoiced* from the lines. The finding tells you what to
do: "merely unassigned" (free lines suffice — assign), "never invoiced"
(more periods than lines — create a catch-up invoice via the draft or
waive the period) or "without period" (more lines than periods — a
subscription is missing in the register or something was billed twice).
Per open period the lines of the same product are listed with their
distance from the period start: free ones with assignment, consumed ones
with their holder for review. The reference date is the invoice's
**service period**, else the invoice date; licences and months are shown
separately ("5 × 12 mo." = five licences for one year). Plus three traps
invisible per subscription: **invoice to a different customer** (the
provider account is not the customer, or the end customer is billed
directly instead of via the partner; recognised by a shared name part) —
the fix is "Holder → customer": the subscription moves to that customer and
the proposal run applies immediately. **Voided** invoices near the period
start explain an empty period. **Inbox** subscriptions whose company
appears in invoice texts to this recipient are waiting for their holder. A
free line that no longer hits any period of its product means the contract
is missing in the register (the provider export does not know it): the
product row says "Line without subscription from …" and "Create subscription
from line" opens the subscription dialog with article, quantity, start and
price taken from the invoice. Assignment may target a period of another
subscription of the same recipient — never a period of a different customer.
