---
title: "Subscriptions & Licences"
topic: finance.resale
version: 13
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
    - finance.resale.accounts
    - finance.resale.reconcile
    - finance.resale.board
    - finance.resale.subscription
    - finance.resale.periods
    - finance.resale.plan
    - finance.resale.reports
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

**Quantities and coverage:** The register never lets you assign more than
there is. You assign at most what a period still has open — also when moving
a link. Only licences that are neither transferred nor already invoiced to
the holder in the period can be transferred, allocated or taken over; an
assignment to the own holder is refused, and legacy cases appear with
“Return assignment”. A line of a recurring template takes at most as many
licences as it bills (“Template line overbooked” reports legacy cases). If a
template covers only part of a subscription, the invoice proposal contains
the rest (“4 of 9 licences, 5 via recurring template”); the automatic run
leaves it out. An invoice line carries at most as many stock licences as it
has pieces.

**Generic list:** Besides the provider exports (Telekom, Quality Hosting)
the import accepts any CSV or XLSX list whose columns are recognisable by
name — German or English: id, company, product, quantity, start, end,
interval, term, purchase price, sale price, provider, order. Required are
company, product and start. Without an id it is derived from company,
product and start, so a repeated import updates the same subscriptions
instead of duplicating them. The provider comes from the column or the
dialog; a CSV template is available in the import dialog.

**Inbox:** Imported subscriptions whose company the register cannot assign
to a holder yet land in the inbox. Per company you decide once: customer,
end customer of a partner (foreign customer) or own holding — the suggestion
comes from a name comparison with customers and foreign customers and from
the invoice texts: if the company appears in the text of invoices to a
partner, the inbox suggests that partner, and a click opens the assignment as
an end customer. Ended subscriptions also stay here while a due period is
open — without a holder nobody would find their invoices. “Assign holder” next to
“Not assigned yet” (license account, subscription, periods) opens the same
assignment. The
decision is remembered; the next import assigns the same company at once.
Lines the import could not process (unreadable date, quantity without a
number, duplicate identifier) are kept as findings on the import: the count
in the message, the details expandable in the list.

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
voucher mirror (Lexoffice, sevDesk, Akaunting, Invoice Ninja and other
programs; without drafts, cancellations and deleted documents) pro rata: for collective invoices without lines (Telekom)
you enter the provider's share and the service month, the amount is spread
over all periods of the month weighted by their monthly expected purchase.
(3) Domain bookings from domain management automatically. On PDF import the
register checks the total: if the sum of the lines differs from the
document total (e.g. a page was not read), the import still runs and the
difference is shown as a notice. The purchase page filters by provider,
source, date range and search term; an allocation is always released as a
whole per document.

**Product classification:** The list shows every article of the article
catalogue — the active articles of the article master and the articles of
connected accounting systems such as Lexoffice, with their source. easybill,
sevDesk, Invoice Ninja, Akaunting and InvoicePlane deliver their articles
too: the voucher pull reads them before the vouchers, and mirrored lines
then carry the source's article instead of only its identifier. Which of
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
Domains in a subuser account are invoiced by the domain portal itself: it
sends the subuser monthly invoices, which the booking journal assigns to the
periods. These domains need no recurring template and never go into an
invoice draft of the register; the workbook and the subscription page name
the subuser.

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
The tiles above the lists lead to what they count: on licence accounts,
reconciliation, licence stock and in a batch they filter the list, in a
recipient’s workbook they filter the reconciliation board to the affected
products; there a second click on the active tile removes the filter again.

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
it and the weekly digest reminds you. A provider without running
subscriptions, for example after a switch, shows “no running subscriptions”
and does not count as outdated.

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
