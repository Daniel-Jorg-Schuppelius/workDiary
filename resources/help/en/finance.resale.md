---
title: "Subscriptions & Licences"
topic: finance.resale
version: 1
audience: []
modules:
    - module.reselling
related:
    - roles.buchhaltung
    - glossary.core
---

The **resale register** keeps every resold recurring service as a
subscription: Microsoft 365 licences, domains, hosting, mailboxes, backups or
anything else — provider-neutral in one list.

**Holder:** Every subscription has exactly one holder. A **customer** is
invoiced directly. An **end customer** belongs to a partner; the invoice goes
to the partner, who passes it on. **Own holding** (internal licences, own
domains) is never invoiced. Subscriptions without a holder wait for
assignment and count in the “Without holder” figure.

**Term and periods:** From start, billing interval (yearly or monthly) and
end the register plans the expected billing periods — up to 90 days ahead so
the next renewal is visible. A subscription without an end renews
automatically. A remainder at the end shorter than one month (yearly) or five
days (monthly) is an alignment stub, not a period. Periods with a decision
(billed, partial, waived, disputed) survive every re-planning; open periods
follow changes to quantity, price and end.

**Prices:** Purchase and sale per unit and interval, net. The article supplies
product and sale price for invoices. Expected sale per period = quantity ×
sale price.

**Status:** Active, Cancelled (end known, periods are planned until then),
Superseded (successor at another provider) and Ended. Ended and superseded
subscriptions get no new periods.

**Deleting:** A subscription with decided periods cannot be deleted — set it
to “ended”. Permissions: view with *View resale register*, manage with
*Manage resale register*.

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
with their holder for review. Plus three traps invisible per subscription:
invoices to **related recipients** (sister company, or end customer billed
directly instead of via the partner, recognised by a shared name part —
"Invoiced to …"), **voided** invoices near the period start, and **inbox**
subscriptions whose company appears in invoice texts to this recipient.
Assignment here may also target a period of another subscription of the
same recipient and take a line of a related recipient — never a period of
a different customer.
