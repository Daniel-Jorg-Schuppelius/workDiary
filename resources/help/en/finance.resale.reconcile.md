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

**Billing start of the portal account:** If a sub-user bills their domains
through the provider portal, the portal only invoices since that sub-account
exists. The license account, the workbook, the overview and the domain's
subscription page report open license years that started earlier, together
with the account start. “Apply billing start” decides them in one step: **Do
not invoice any more (waiver)** closes them, with the account start and your
reason as the reason. **Invoice them yourself** keeps them open and includes
them in the register's invoice proposal although the domain is otherwise
billed through the portal. License years that started after the account start
and partially invoiced ones remain untouched; afterwards each period can be
reopened or waived individually.

**Link findings:** Above the board you see links that cannot be right: a line
covers more license months than it has (overbooked), or its service period
misses the billing period (misplaced). A misplaced link can be moved to the
suggested period or removed, an overbooked one can only be removed.

**Reorder product:** If the links of a product are mixed up, for example
shifted by a year, the button in the product header removes all invoice links
of that product for this recipient, and the proposal run redistributes the
invoices. Confirmed and manual links are lost. Waivers, disputes and credit
notes stay.

**Invoice sent to another customer:** If another customer has a free line of
the same product with exactly the service period of the open period, it is
shown at the period, even for a single license. From there you can change the
holder or assign the period.

**Surplus line:** If all periods of a product are covered and an invoice stays
free, it is shown as “billed twice” or “billed after the end” on the board. Either it was invoiced twice
(credit note), or the subscription is missing in the register (create
subscription), or the line does not belong here (ignore). If you create the
subscription from the line and a subscription of the same product ended shortly
before, the dialog offers it as predecessor.

**Too much, differing, missing — in euros:** The reconciliation list and the
overview show per invoice recipient what was billed too much (a line in a
licence year that is already covered: “billed twice”; a line after the last
licence year of the product: “billed after the end”) and what was billed
differently. What is missing is shown as the open amount. Lines “outside the
term” or with “no subscription” are not counted: there a subscription is
usually missing in the register.

**Deviating link:** If you link a line whose article is not the one of the
subscription, or whose amount differs from the target by more than 5 % (at
least €1), the dialog asks for a reason. The link remains possible; the reason
is stored on the period. Existing links without a reason appear among the
findings as “Different article” or “Amount differs” and can be justified or
removed there.

**Licence picture and correction sheet:** The workspace shows product ×
licence year across all providers of the customer at the top. The correction
sheet below lists the invoices to credit, the licence years to bill, the
deviations and the balance; it can be downloaded as XLSX. A credit note that
refers to the invoice removes its lines from the list.
