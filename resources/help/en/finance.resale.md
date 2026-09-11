---
title: "Subscriptions & Licences"
topic: finance.resale
version: 2
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

**Inbox:** Imported subscriptions whose company the register cannot assign
to a holder yet land in the inbox. Per company you decide once: customer,
end customer of a partner (foreign customer) or own holding — the suggestion
comes from a name comparison with customers and foreign customers. The
decision is remembered; the next import assigns the same company at once.
Lines the import could not process (unreadable date, quantity without a
number, duplicate identifier) are kept as findings on the import: the count
in the message, the details expandable in the list.

**Periods:** The period page shows the due periods of all subscriptions with
status tiles (open, billed, partial, waived, disputed). "Compute proposals"
matches the open periods against the licence lines of the mirrored invoices
and creates proposals; you confirm them, link a line by hand (only invoices
of the same recipient, only free licence months) or waive with a reason
("goodwill"). Decided periods are no longer touched by planning; "reopen"
opens them again. If a linked invoice is later voided in Lexoffice, the next
run sets the link to zero months, notes the cancellation and reopens the
period so the replacement invoice can be linked.

**Invoice draft:** From all open periods of an invoice recipient a draft is
created with one click — with Lexoffice invoicing as a draft in Lexoffice
(nothing is finalised; you review and issue there), with local invoicing as
a local invoice draft with lines and proposed links. One line per
subscription and period, end customer in the description, quantity in
months for monthly articles. The periods remember the draft: a second click
does not create a second one but names the pending draft with number and
date; only when the draft became an invoice or the period is decided are
they free again. The dialog lists only recipients with open periods and a
sale price and names below what is already in a draft. Creating requires
the right *Create invoice drafts from periods*.

**Recurring invoicing:** With local invoicing the register can create the
drafts itself every day. The switch is on the *Product classification*
page (right *Manage resale register*): active = the next run creates, for
each invoice recipient with due periods not yet drafted, the same draft as
the click — lines per subscription and period, periods linked as proposals
and stamped. The *lead time* in days also takes periods before they start
(0 = due periods only). Recipients invoiced via Lexoffice/DATEV are left
untouched, periods without a sale price are skipped, own holdings are
never invoiced. You finalise the drafts in the invoice list; the weekly
digest names how many drafts of the last seven days are still open. Once
or as a trial: `php artisan resale:draft-local --organization=… --dry-run`
(`--force` overrides the switch).

**Purchase entries:** The actual purchase per subscription and period comes
from three sources: (1) provider invoices and credit notes as PDF (Quality
Hosting, German and English layout) — each line names contract, end
customer and term, the amount goes exactly to the period; credit-note lines
without a contract belong to the company. (2) Incoming vouchers from the
voucher mirror pro rata: for collective invoices without lines (Telekom)
you enter the provider's share and the service month, the amount is spread
over all periods of the month weighted by their monthly expected purchase.
(3) Domain bookings from domain management automatically. On PDF import the
register checks the total: if the sum of the lines differs from the
document total (e.g. a page was not read), the import still runs and the
difference is shown as a notice. The purchase page filters by provider,
source, date range and search term; an allocation is always released as a
whole per document.

**Margin report:** Per product and per invoice recipient the due periods of
the range are shown with expected sale (quantity × sale price), billed (net
amounts of the invoice links, proposals included), expected purchase
(provider price × quantity) and actual purchase from the purchase entries.
Margin = billed − purchase; the actual purchase counts as soon as every
period of the row has one, otherwise the expected purchase. Amounts are
never summed across currencies — with several currencies there is one row
per currency and a notice. Export as CSV, XLSX or PDF; the invoice proposal
(open periods with open licence months and amount) as CSV or XLSX.

**Price check:** Per product the purchase per contract, catalogue price and
RRP from the last imported price list against the sale prices of the
subscriptions (minimum, median, maximum). Flags: "sale below purchase",
"sale below RRP", "contract above catalogue", "no sale price".

**Product classification:** Which Lexoffice articles are subscription
products, the register detects by name. Per article you can override:
"subscription product" forces detection, "never a subscription line" keeps
services with a product name in the text (maintenance on Exchange) out of
proposals, invoice lists and "lines without subscription". The same
classification exists for the active articles of the local article master
(section *Local articles*): it decides which lines of local invoices the
mirror treats as licence lines, and the price check compares the article's
sale price with the subscription prices.

**Contracts:** A subscription can carry a contract from contract management
as its deadline frame ("Contract" field in the subscription dialog; only
customer contracts whose partner is the subscription's invoice recipient).
The contract file lists its subscriptions in the "Subscriptions & licences"
panel. No second deadline catalogue: the daily run posts one date per
subscription to the contract calendar — the notice deadline at the end for
cancelled subscriptions, a renewal warning before the next period for
automatic renewal, each brought forward by the contract's notice period (30
days if none is set), with 14 days' advance warning. If the end changes, the
date moves with it; completed dates stay completed; ended subscriptions or
subscriptions without a contract close their open date.

**Domains:** Every domain from domain management becomes a "Domain"
subscription daily, with a yearly interval from registration, purchase =
renewal price and the holder from domain management as long as the register
has not decided one. The sale price per TLD comes from the price catalogue
(provider domain reselling, product e.g. ".de"), the article from the
Lexoffice article for the TLD; manually maintained prices, articles and
holders survive every run. Vanished domains end on the reference day; if a
run's domain list is empty, nothing is ended. Domain subscriptions and their
purchase entries cannot be created by hand — they only come in through the
sync.

**Renewals and subscriptions without invoice:** The "Renewals" report shows
which subscriptions renew or end in the range (tiles 30, 60, 90 days):
renewal is the start of the next planned period, for cancelled
subscriptions the end counts. "Without invoice" lists subscriptions whose
oldest open due period is older than N days (default 60), with open periods
and open amount. Both as CSV or XLSX.

**Dashboard tile:** The tile "Open subscription periods" (group Finance, off
by default) shows open periods with open amount, unconfirmed proposals and
subscriptions without holder and leads to the respective page.

**Import findings:** Every import (Telekom, Quality Hosting, price list,
generic list) logs counters and findings per line. Dates must be dates
(Excel date cells are read; "3.2026" or "2026" alone are not), quantities
numbers, identifiers unique within a file — otherwise the line is skipped
and the reason is named.

**Retention of import files:** Uploaded import files (they may contain end
customer names) stay in the storage folder for 90 days and are then deleted
by the schedule; the import record with its counters remains. By hand:
`resale:prune-imports` (--days changes the period).

**Repairing invoice links:** If the voucher mirror was rebuilt earlier with
new line IDs, confirmed links point to nothing (link without line text,
period counts as uncovered). The command `lexoffice:repair-resale-links`
re-attaches such links via invoice number and licence line; what is not
unambiguous is only listed (--dry-run shows beforehand what would happen).
