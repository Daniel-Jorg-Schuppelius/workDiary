---
title: "Document flow"
topic: billing.feed
version: 5
keywords:
    - invoices
    - quotes
    - outgoing invoices
    - incoming invoices
    - credit notes
    - expenses
    - open items
    - accounts receivable
    - dunning
    - overdue invoices
    - e-invoice
    - document list
    - project filter
audience: []
modules:
    - module.vertrieb
    - module.finance
related:
    - billing.chain
    - invoices.manage
    - quotes.overview
    - finance.incoming-invoices
    - travel-expenses.manage
---

The document flow shows **all documents in one list**: quotes, sales and
purchase invoices, credit notes, documents mirrored from a connected
accounting system, and expenses. The former "Quotes" and "Invoices" pages lead
here – they are now tabs of the same list.

**Period:** The list follows the date filter in the header. If a document is
missing, check the selected period first. A hit from the global search opens
the list on the document date; that day then applies instead of the header
period.

**Tabs:** "All", "Quotes", "Sales invoices", "Purchase invoices", "Credit
notes" and "Expenses" are saved filters, not separate pages. The number on a
tab is the count of documents in the period. "Other" (order confirmations,
delivery notes, miscellaneous) only appears when it contains something.
Search and filters are kept when you switch tabs.

**Key figures:** The tiles are calculated over the whole filtered set, not
just the visible page – separately per currency and without conversion.

Clicking **Open** filters for open documents (a second click removes the filter); the total tiles have no target.

- **Revenue**, **Cost (external)** and **Balance** compare outgoing and
  incoming documents.
- **My expenses** shows your own expenses and the share still under review.
- **Open** and **of which overdue**: overdue is a subset of open, so the two
  amounts are not added up. Clicking the tile filters for overdue documents.
- **No monetary effect:** quotes, order confirmations and delivery notes only
  count as a number.

**Filters:** The search finds number, customer and supplier. You can also
filter by origin (created in WorkDiary or coming from a connected system),
assignment (customer or supplier), status (Draft, Open, Closed, Cancelled),
"Overdue only" and "Include archived". The direction can only be chosen in
"All" and "Credit notes", because the other tabs already fix it. In the
"Expenses" tab, "Unlinked only" shows expenses that have no accounting
document yet; administrators switch between "Mine" and "All" there.

**Rows:** The number leads to where the transaction is handled – the invoice,
the quote, the purchase invoice or the expense receipt. Mirrored documents
without a page of their own have no link. For open documents the "Due" column
shows the days overdue and the reminder level reached. You send a reminder for
your own overdue invoices straight from the row with "Send reminder".

**Mirrored documents:** If the connected system no longer holds a document, it
shows **Deleted in source**. It then counts in no key figure and appears only
with "Include archived". For documents in a foreign currency, the equivalent in
the second currency is shown below the amount.

**New documents:** At the top right you create a quote or an invoice, or
convert an invoice file into an e-invoice. "To bill and to follow up" opens
the document chain with everything that is still outstanding.

**Visibility:** The document flow only shows what your permissions in the
individual areas allow anyway. If you lack the permission for quotes, for
example, the tab and its rows are missing.

## Duplicate documents and the original

**External leads:** If an invoice was transferred to an accounting program or
the program assigned its number (Lexoffice, orgaMAX, sevDesk, easybill), it
appears only once in the list – as a mirrored document. This applies to every
connected program as soon as an outgoing document carries the same number and
belongs to the same customer: a business issues an invoice number only once. A
document with the same number but a different customer does not replace your
invoice; neither do incoming documents or documents deleted in the source.

**Expenses:** Once an incoming document is linked to an expense, only the
document counts as expense – with Lexoffice as with sevDesk, Akaunting, Invoice
Ninja and the other programs with a document mirror.

**Totals:** Mirrored incoming documents count as expense, credit notes reduce
it; drafts and cancelled documents do not count.

**Original:** The number of a mirrored document opens the original in the
program where one has an address (Invoice Ninja, Akaunting). For sevDesk
invoices and credit notes, easybill documents and orgaMAX invoices,
**Original PDF** at the end of the row loads the PDF straight from the program;
this requires the right to view documents, and for the orgaMAX PDF also
administration access. sevDesk vouchers without an invoice (till receipt,
supplier voucher) and orgaMAX expenses have no PDF.

**orgaMAX and sevDesk:** orgaMAX mirrors invoices, deposit invoices and
expenses; the expenses appear as incoming documents under expense. Credit notes
from sevDesk appear as outgoing credit notes and reduce revenue. A program's
recurring templates are not documents and do not appear in the document feed;
they are part of the subscriptions and licences reconciliation.

## Filter by project

The **Project** filter shows only documents of one project. Documents from
accounting programs have no project and drop out with this filter.
