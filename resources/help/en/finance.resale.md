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

**Generic list:** Besides the provider exports (Telekom, Quality Hosting)
the import accepts any CSV or XLSX list whose columns are recognisable by
name — German or English: id, company, product, quantity, start, end,
interval, term, purchase price, sale price, provider, order. Required are
company, product and start. Without an id it is derived from company,
product and start, so a repeated import updates the same subscriptions
instead of duplicating them. The provider comes from the column or the
dialog; a CSV template is available in the import dialog.

**Transferring licences:** When two companies share premises and the second
one uses part of the licences of a contract, transfer those licences at the
contract ("Transfer licences": holder, quantity, period, sale price). A
separate subscription for the other holder with its own billing periods
appears; the contract plans its periods with the remainder. Each holder
gets its own invoices assigned. When a successor replaces the contract
(import), the transfer continues there. A contract with transfers can only
be deleted once the transfers are gone. A holder change over time — a
company splits and the new one takes over the contracts — is a transfer
too: all licences for the old period to the former holder; the
reconciliation offers this at an invoice of the other customer as
"Transfer period to …", prefilled.
