---
title: "Subscriptions & Licences"
topic: finance.resale
version: 9
keywords:
    - Import preview
    - Bulk actions
    - subscription billing
    - recurring services
    - license resale
    - reselling register
    - billing periods
    - renewals
    - notice period
    - margin report
    - recurring invoice
    - import subscriptions
    - hosting billing
    - Lexoffice
    - Telekom
    - Quality Hosting
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
In the subscription list, filter “Only without holder”, select several
subscriptions and assign them with “Assign holder” in one step to a customer,
an end customer or your own holding; a single subscription goes via the icon
in its row. Domains receive the holder in the domain management as well, and
the inbox leads subscriptions without a company name there.

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

**Product classification:** The list shows every article of the article
catalogue — the active articles of the article master and the articles of
connected accounting systems such as Lexoffice, with their source. Which of
them are subscription products, the register detects by name. Per article
you can override: "subscription product" forces detection, "never a
subscription line" keeps services with a product name in the text
(maintenance on Exchange) out of proposals, invoice lists and "lines without
subscription". The classification works the same for every source: it
decides which invoice lines the mirror treats as licence lines, and the
price check compares the article's sale price with the subscription prices
(a monthly price counts twelve times for a yearly subscription).

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
article catalogue (article master or accounting system) for the TLD; manually maintained prices, articles and
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

**License account:** An account is named after its holder; if the invoice
goes to a partner, the partner is shown next to it (“via LDS Systems”), and
the partner's recurring templates appear only in the end-customer account
they are assigned to. The *License accounts* tab shows per provider account
(customer number at the provider, including predecessors of a provider
change) how many licences per product are running, who holds them and which
licence year is invoiced — as a grid product × holder × year with invoice
number or "missing". If several companies hold licences of the same account,
maintain the **allocation** per product ("Märkische Bunker 5 × Exchange from
18.10.2025"): other holders fill the contract with the largest quantity
first, then by start; the contract holder keeps the rest. Per-contract
transfers are derived from this, follow every change and move to the
successor on a provider change; manual transfers remain possible as an
exception. The schedule also replans superseded and ended contracts of the
last three years so a later allocation still changes their target.
**Findings on links** show lines covering more licence months than they have
(overbooked) and links whose service period does not meet the period — with
"Move to …" onto the matching period of the same subscription or "Unlink";
the link dialog and the quick assignment warn beforehand with ⚠.

**Recurring templates (plan):** If an accounting program issues the invoices,
recurring invoices are created there as recurring templates. The register
mirrors them (Lexoffice: daily and after the voucher sync, read-only) and shows
per template in the license account its status, rhythm, next run and the
licence lines with the subscriptions they invoice. The subscription ↔ template
line assignment is proposed by the run — first via the invoice the template
last created that is assigned to a period, otherwise via same recipient,
article and quantity (with several end customers of a partner only when
named) — you confirm, assign manually or
remove. Periods of a subscription with a running template are left out of the
invoice proposal. **Checks stock ↔ plan** report before the invoice: subscription
without template, template without subscription ("Create subscription from
template" takes over article, quantity, rhythm and price), differing quantity,
template running past the subscription end, template paused or ended while the
subscription runs, next run more than seven days off the period start, last run
failed — in the tile, the weekly digest and the work queue. Every mirrored
invoice carries the reference to its template.

**Overview and tabs:** The entry page “Subscriptions & Licences” is a
**task list in four groups** — Invoice (due service without invoice, only
after 21 days of grace and never while an active recurring template still
has its run for the period ahead; plus findings on invoice links,
allocations above the holdings, unit licences without invoice), Assign
(subscriptions without holder, licence lines without subscription), Check
plan (findings on recurring templates, i.e. what the next invoice gets
wrong) and Provider state (outdated imports, renewals within 30 days). Each
row shows count and amount and jumps to the page where the decision is
made. Next to it the **state**: the last proposal run, unconfirmed proposals
as a spot check, periods planned via recurring template or still within
grace, per provider the last import with its age. The tabs follow the
workflow: Overview · Licence accounts · Reconciliation · Periods · Inbox │
Subscriptions · Licence stock · Purchasing │ Reports · Prices; product
classification and billing settings live in the ⋯ menu.

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

**Product classification with filter:** The classification shows by default
only subscription products and articles with subscriptions or template
lines; “All articles”, “Without classification” and the search by name or
number narrow it down. If an accounting program issues the invoices, each
article shows in how many active recurring templates it appears.

**Import with preview:** After uploading, the register first shows what the
import would change — per file new, changed (quantity, price, status, start,
end with the previous value), unchanged and the plannable subscriptions of
the provider that the file no longer lists (the import does not end them).
It is applied only after “Apply”; “Discard” deletes the files. If the
provider changes a price, the import follows with the subscription’s
purchase price as long as it equals the last provider price — a manually
maintained price stays — and writes a catalogue line as of the file date
(date in the file name such as “2026-10-26_Export.xlsx”, otherwise the import
day). If a provider’s last import is older than 30 days, the overview shows
it and the weekly digest reminds you.

**Purchases from the invoice inbox:** In the purchasing tab, use “Map
suppliers” to map a supplier of the master data to the provider (the name
gives a suggestion; nothing is mapped automatically). As soon as an incoming
invoice of this supplier is assigned in the invoice inbox, the register
takes it over as a purchase: Quality Hosting line by line through the
invoice reader (contract and term per line), domain providers per domain
name onto the domain subscription (only if the registrar’s accounting
journal does not already deliver the costs), all others pro rata onto the
periods of the invoice month. Every line carries the incoming invoice as
origin; a second run recognises it by hash. Lines that match no
subscription remain “without subscription” — the filter “Without
subscription only” shows them, “Assign subscription” attaches them to the
right subscription and the period at the document date. `resale:import-incoming
--since=` catches up assigned invoices retroactively. The licence account
shows expected and actual purchase and the margin of the last twelve months
per product; the weekly digest reports subscriptions whose contract price
differs from the last imported catalogue or provider price.

**Invoice link on unit licenses:** When selling a license from stock you
pick the invoice line from the accounting program that bills this license;
the list shows the lines of the chosen customer around the sale date, with
matching ones (same article or product name) first and suggested. Without a
line the invoice reference stays free text. License list, batch page and
customer file show the voucher number of the linked line with a jump to the
accounting program and the voucher image. A sale that carries neither line nor
reference after 30 days appears as the check case “Unit licenses without
invoice” in the overview, in the recipient’s reconciliation and via the
“Without invoice” filter in the license stock; add the link via “Correct
sale”. In the customer portal customers see their sold licenses with the
invoice number, never the key.
