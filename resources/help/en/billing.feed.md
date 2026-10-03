---
title: "Document flow"
topic: billing.feed
version: 1
audience: []
modules:
    - module.vertrieb
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
missing, check the selected period first.

**Tabs:** "All", "Quotes", "Sales invoices", "Purchase invoices", "Credit
notes" and "Expenses" are saved filters, not separate pages. The number on a
tab is the count of documents in the period. "Other" (order confirmations,
delivery notes, miscellaneous) only appears when it contains something.
Search and filters are kept when you switch tabs.

**Key figures:** The tiles are calculated over the whole filtered set, not
just the visible page – separately per currency and without conversion.

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

**New documents:** At the top right you create a quote or an invoice, or
convert an invoice file into an e-invoice. "To bill and to follow up" opens
the document chain with everything that is still outstanding.

**Visibility:** The document flow only shows what your permissions in the
individual areas allow anyway. If you lack the permission for quotes, for
example, the tab and its rows are missing.
