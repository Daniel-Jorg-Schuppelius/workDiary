---
title: "Posting and inbox"
topic: accounting.posting
version: 5
keywords:
    - post entry
    - journal entry
    - book receipts
    - account assignment
    - posting suggestion
    - posting rules
    - reverse posting
    - cancel posting
    - four-eyes principle
    - approval
    - foreign currency
    - exchange rate
    - finalize posting
    - general journal
    - open items
    - recurring entry
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.overview
    - accounting.closing
---

The **posting inbox** is the entry point: it shows documents, expenses, cash
and payment items of the period with their posting status — unposted, blocked,
ready, posted. Blocked items come first; that is the work someone has to touch.

**Proposal before posting.** Even an unambiguous proposal only becomes a
reviewed draft, never a direct posting. With the four-eyes principle active,
the preparer does not post it themselves.

**Four-eyes principle for direct postings.** Some actions create their entry
themselves: **Discount** and **Write-off** via **Settle** in the open items,
**Post to clearing account** in the payment reconciliation, the **Internal
transfer**, **Import opening balances**, **Post special prepayment** and
**Reverse** in the journal. Without the four-eyes principle they post
immediately. With it active, a reviewed draft is created instead: a message and
a note in the dialog say so, and the posting inbox lists it with its kind
(**Discount/write-off**, **Clearing entry**, **Internal transfer**, **Opening
balances**, **Special prepayment**, **Reversal**) and the status **Ready** –
regardless of the period in the header, as long as **All sources** is selected.
The action only takes effect once a second person posts it with **Post** or
**Accept and post all**: only then is the open item settled, the bank
transaction booked and the special prepayment credited in the last advance
return of the year. In the journal, **Post immediately** stays blocked for the
person who creates the entry.

**Discard draft.** You delete a pending draft from these actions with
**Discard draft** in the posting inbox or on its detail page (permission
**Post entries**, with confirmation). The step is logged and the action is
open again afterwards: the item can be settled again, the bank transaction,
opening balances and special prepayment can be booked anew, the entry of a
discarded reversal can be reversed again, and an internal transfer is removed
together with the link to its documents. Posted entries and drafts from
posting proposals cannot be discarded.

**Blocked instead of guessed.** If a posting rule is missing, the proposal
names role and criteria. A guessed default account would only show up in the
reports — when the entry is already posted.

**Correction only by counter-entry.** A posted entry is immutable. The reversal
creates a mirrored counter-entry with a mandatory reason; the original
remains.

**Documents in foreign currency.** Sales and purchase invoices and expenses in
a foreign currency are converted at the monthly rate of their document month
(Section 16(6) UStG). Maintain the rates under “Exchange rates” (next to the
posting rules), individually or line by line, e.g. from the Ministry of Finance
publication. Without a rate, the document stays in the inbox with a note. Rate
and original amount are part of the posting evidence. Payments, cash and assets
in foreign currency are still not posted; exchange differences on settlement
are booked manually.

## Journal

Open the **Journal** under **Sales & billing** → **Accounting** → **Journal**.
It lists all prepared and posted entries in the period selected in the header.
The **Journal**, **Open items** and **Recurring** menu items appear as soon as
your organization keeps, or has kept, local accounting; if another system
currently keeps the general ledger, a note above the list says so.

- **List:** **No.**, **Posting date**, **Description**, **Accounts**,
  **Amount** and **Status** (**Draft**, **Reviewed**, **Posted**,
  **Reversed**). The search finds description and document, the status filter
  shows a single state. WorkDiary assigns the journal number only when an entry
  is posted, consecutively and without gaps.
- **New entry:** the dialog posts one amount from a **Debit** account to a
  **Credit** account. **Posting date**, **Description**, both accounts and
  **Amount** are required; **Document date**, **Document** and – if cost
  centres are set up – a **Cost centre** for both lines are optional. Only
  active accounts can be selected. Without **Post immediately** a draft is
  created.
- **Viewing an entry:** the detail page shows **Entry header** and **Entry
  lines** with debit and credit totals, plus notes on a reversal and on
  exceeded monthly budgets (without blocking). Drafts and reviewed entries are
  posted here with **Post**.
- **Reversing:** correct a posted entry with **Reverse**: the **Reason** is
  required, the **Posting date of the counter-entry** is optional. Left empty,
  the original day applies as long as its period is open, otherwise today.
  **Create counter-entry** posts the mirrored entry and also reverses the open
  items that resulted from the original. With the four-eyes principle active,
  the counter-entry is created as a draft: the entry stays posted and the open
  items unchanged until a second person posts the reversal. Until then no
  second reversal of the same entry is possible; its detail page points to
  the draft with **Show pending draft**. The automatic reversal when an
  allocation is removed in the payment reconciliation always posts
  immediately.

When posting, WorkDiary checks: an open period exists for the posting date and
local accounting keeps the general ledger on that day; debit and credit are
equal; all accounts are active, and an account marked **Cost centre required**
has a cost centre. With the four-eyes principle active, the person who created
the entry may not post it – not even via **Post immediately**.

**Permission:** viewing with **View accounting**, entering with **Prepare
postings**, posting and reversing with **Post entries**.

## Open items

**Sales & billing** → **Accounting** → **Open items** shows receivables and
payables from posted entries that are not yet settled – independent of the
period in the header. An open item is created when an entry is posted to an
account with the **Open items** flag; payments settle it through the payment
reconciliation.

- The **Receivable** and **Payable** tabs separate the two directions.
- The tiles total the open amounts by age from the due date: **Not due**,
  **1–30 days**, **31–60 days**, **61–90 days** and **over 90 days**.
- The list, sorted by due date, shows **Document**, **Counterparty**,
  **Document date**, **Due** (with the note “… days overdue”), **Original**,
  **Open** and **Status** (**Open**, **Partially settled**, **Disputed**).
  **Show entry** opens the underlying entry.
- **Settle** records a deduction without payment: **Discount**, **Retention**
  or **Write-off**, with **Amount** and an optional **Note**. The amount may
  not exceed the open remainder. For a discount or write-off, WorkDiary also
  posts a counter-entry in the journal – to the discount or write-off account
  from the DATEV settings, provided that account exists in the chart of
  accounts. A retention creates no entry.
  With the four-eyes principle active, the counter-entry is created as a
  draft in the posting inbox and the item stays open until a second person
  posts it. Until then the list shows **Draft awaiting approval**, **Show
  pending draft** leads to the entry, and any further settlement of the item –
  including a retention – is refused. If the item has been settled otherwise
  by the time of approval, posting fails because the amount exceeds the open
  remainder. A retention and a settlement without an existing counter account
  create no entry and apply immediately.

**Permission:** viewing with **View accounting**, settling with **Post
entries**.

## Recurring

Under **Sales & billing** → **Accounting** → **Recurring** (page **Recurring
items**) you plan what comes up regularly. There are two kinds of template:

- **Document expectation:** for a document that must arrive regularly, such as
  rent or leasing. It creates neither a document nor an entry, but on the due
  date an open item with the status **Document expected** – so it stays visible
  that the original is still missing.
- **Posting template:** on the due date it creates a draft entry with debit and
  credit account and the expected amount, dated on the due day. It never posts
  by itself; you do that manually in the posting inbox or in the journal.

The page is divided into **Open items** (**Template**, **Period**, **Due**,
**Expected**, **Status**; for **Blocked** the reason is shown below, for
**Draft created** **Show entry** leads to the draft, for **Document expected**
**Assign document** assigns the original), **Templates** (**Name**,
**Kind**, **Interval**, **Next due**, **Responsible**, **Status** with version
number) and **Invoice schedules**: active billing plans for reference only,
edited via **Open billing plans**.

**Add template:** **Kind**, **Name**, **Interval** (**Monthly**,
**Quarterly**, **Semi-annually**, **Annually**), **Due day** (1–28, so every
month has that day), **Expected**, **Start** and optionally **End**, for
posting templates also **Debit** and **Credit**, plus **Responsible** and a
**Note**. A posting template without both accounts and an amount is not saved.
When editing, the saved accounts are preselected and the dialog shows the next
due dates; every change saves a new version, and items already created stay
unchanged.

**Process and rules:**

- A daily run creates the due items as long as local accounting keeps the
  general ledger on the reference date. At most one item is created per
  template and period.
- **Run now** creates the item for the next due date immediately, without
  waiting for the daily run.
- If a draft cannot be created, for example because no period exists for the
  date, the item appears as **Blocked** with its reason.
- **Assign document** fulfils a document expectation: you choose the incoming
  invoice in the **Incoming e-invoice** field. You can choose invoices from
  **Incoming e-invoices** that you may see, that are not rejected and that are
  not yet assigned to an item – one invoice fulfils at most one item. The item
  then has the status **Fulfilled**.
- When the draft of a posting template is posted, its item also has the status
  **Fulfilled**.
- If an item with **Document expected** or **Draft created** is overdue,
  WorkDiary reports it once through the notifications, by default to
  accounting and to the person under **Responsible**.
- **Pause** stops a template; **Resume** continues with the next due date from
  today on, without catching up on missed ones. **End** stops the template for
  good; items already created remain.

**Permission:** viewing with **View accounting**; adding, editing, pausing,
resuming and ending templates with **Set up accounting**; **Run now** and
**Assign document** with **Prepare postings**.
