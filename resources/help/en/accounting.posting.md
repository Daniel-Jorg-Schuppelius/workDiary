---
title: "Posting and inbox"
topic: accounting.posting
version: 10
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

**Discard draft.** You delete any draft – from one of these actions, from a
posting proposal in the inbox, from a posting template or entered by hand –
with **Discard draft** in the posting inbox or on its detail page (with
confirmation). Drafts from the actions named above are discarded by whoever may
**Post entries**; for all others **Prepare postings** is enough. The step is
logged and the action is open again afterwards: the item can be settled again,
the bank transaction, opening balances and special prepayment can be booked
anew, the entry of a discarded reversal can be reversed again, an internal transfer is removed
together with the link to its documents, a bank transaction set aside by
**Post to ledger account** is open again, and the document
of a posting proposal appears unposted in the inbox again. The item of a
posting template counts as **Skipped**; the next period runs normally. Posted
entries cannot be discarded.

**Edit draft.** As long as an entry is not posted, you change it on its detail
page with **Edit draft**: posting and document date, description, document,
counterparty and the lines with account, amount, tax code, cost centre and line
text. This applies to manual entries, drafts from posting templates and drafts
from posting proposals. Whoever changes the draft counts as its preparer – with
the four-eyes principle someone else posts it. The change is logged. Drafts
from cash discount, write-off, clearing, transfer, bank transaction without
document, opening balances, special prepayment and reversal follow their action
and cannot be rewritten; on any draft, however, **Cost centres** lets you add
the cost centre per line – for example when an account with **Cost centre
required** prevents posting. Posted entries remain unchanged.

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

**Incoming invoices per tax rate.** If an incoming invoice carries several tax
rates – in the tax breakdown of the e-invoice or as rows in the **Enter
values** dialog – the proposal books expense and input tax per rate, each with
the input tax code of that rate. This keeps field 66 and the cash discount per
rate right. If the breakdown does not add up to the gross amount, a single line
from the header values remains.

**Reverse charge (recipient liable).** If incoming invoices name § 13b UStG or
an intra-Community acquisition as the **tax case**, the invoice is net. The
proposal books expense against the payable plus the self-assessed output tax on
the credit side and the same input tax on the debit side – with the code of
that tax case: fields 46/47 for services from another EU country, 84/85 for the
other cases of § 13b, 89 or 93 for the acquisition, input tax in 67 or 61. The
rate is that of the document, at 0 % the standard rate. If the document still
shows tax or the code is missing, it stays blocked with a note. A cash discount
on such an invoice is net: it reduces the tax base, and output and input tax
fall by the same amount (§ 17 (1) sentence 5 UStG); if the discount account is
a DATEV automatic account, the discount reduces the expense account of the
document. Posted incoming invoices whose document names reverse charge but
whose entry carries no tax are listed in the **Posting quality** report;
nothing is posted automatically.

**Outgoing invoices under § 13b.** If a domestic customer is recorded as
recipient under § 13b (e.g. construction services to construction
businesses), the invoice goes out without VAT and with the note “Reverse
charge: recipient liable for tax”. The entry carries the code of field 60,
with the chart template on revenue account 8337 (SKR03) or 4337 (SKR04);
intra-Community supplies go to 8125 or 4125.

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
- **New entry:** the dialog posts any number of lines. **Posting date**,
  **Description** and at least two lines with **Account** (account number,
  with a suggestion list) and an amount in **Debit** or **Credit** are
  required; **Document date**, **Document**, **Counterparty**, **Tax code**,
  **Cost centre** and **Line text** are optional. Empty lines are ignored.
  With a **Tax code** you enter the net amount; the tax line on the tax
  account of the code is created on saving, and the entry appears in the field
  numbers of the VAT return. The **Counterparty** (customer or supplier)
  applies to lines on open item accounts and to lines with a tax code – so the
  open item carries the customer. Only active accounts and tax codes valid on
  the posting date can be chosen. Without **Post immediately** a draft is
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
  or **Write-off**, with **Amount** and an optional **Note**. The amount must
  not exceed the open remainder. For discount and write-off WorkDiary also
  posts a counter-entry to the journal – to the **cash discount account** or
  **write-off account** from the DATEV settings (empty = default of the chart of accounts: 8736 and 2400 in SKR03, 4736 and
  6930 in SKR04). A payable uses its own accounts: cash discounts received
  (3736 or 5736) and, for the write-off, other income (2709 or 4839).
  A cash discount also reduces the tax (§ 17 UStG): WorkDiary splits the
  amount by the tax rate of the document into net (discount account, with tax
  code) and tax share (output or input tax account) – proportionally for
  several rates, each on the discount account of its rate as long as no own
  account is configured. The field numbers of the VAT return and the VAT
  preview show the reduction. If the entry of the item carries no tax (small
  business, tax-free, opening balance), the discount stays
  undivided; the dialog says which applies. The same holds for the discount
  from payment reconciliation. Discounts posted earlier without tax adjustment
  are named in the **Posting quality** report with their calculated tax share;
  nothing is posted automatically. If this
  account is missing in the chart of accounts, the settlement is rejected with
  a message and the item stays open; the dialog warns beforehand. A retention
  creates no entry. With the four-eyes principle the counter-entry is created
  as a draft in the posting inbox and the item stays open until a second
  person posts it. Until then the list shows **Draft awaiting approval**,
  **Show pending draft** leads to the entry, and any further settlement of the
  item – even a retention – is rejected. If the item is settled otherwise
  before approval, posting fails because the amount exceeds the open
  remainder. Discount and write-off settlements from earlier times that were
  closed without an entry are named in the **Posting quality** report; they
  have to be posted manually.
- **Mark as disputed** flags an open item as **Disputed** with a mandatory
  reason. It stays open and can be settled, even after a partial settlement.
  On the outgoing invoice WorkDiary sets a dunning block unless one already
  exists – the item is dunned neither individually nor in the dunning run –
  and in the liquidity overview and forecast a disputed receivable does not count as expected inflow. The incoming
  invoice of a disputed payable is blocked in the payment proposal (**Open item
  disputed**) until the mark is withdrawn. **Withdraw dispute** removes the mark and the
  dunning block it created; a manually set dunning block remains. The filter
  above the list shows **Open**, **Partially settled** or **Disputed**. Both
  steps are recorded in the change log.

**Permission:** viewing with **View accounting**, settling with **Post
entries**.

## Recurring

Under **Sales & billing** → **Accounting** → **Recurring** (page **Recurring
items**) you plan what comes up regularly. There are two kinds of template:

- **Document expectation:** for a document that must arrive regularly, such as
  rent or leasing. It creates neither a document nor an entry, but on the due
  date an open item with the status **Document expected** – so it stays visible
  that the original is still missing.
- **Posting template:** on the due date it creates a draft entry with the lines of the
  template, dated on the due day. It never posts
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
posting templates also the posting lines, plus **Responsible** and a
**Note**. A posting template without at least two lines with account and
amount is not saved. When editing, the saved lines are prefilled and the dialog shows the next
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

**Lines of the template.** A posting template takes the same lines as a manual
entry: several lines, **Tax code**, **Cost centre** and **Counterparty**. Debit
and credit must balance, otherwise the template is not saved. If **Expected**
is left empty, the posting total applies. If the rate of a tax code changes,
save the template again. If you discard the draft of a template, its item
counts as **Skipped**.

**Permission:** viewing with **View accounting**; adding, editing, pausing,
resuming and ending templates with **Set up accounting**; **Run now** and
**Assign document** with **Prepare postings**.

## Sales without tax and expenses

The chart of accounts templates have their own revenue accounts for the tax codes
RC21 (field 21), AUSF (field 43) and NSTB (field 45); charts created from the
template receive them with the update. **Expenses** book input tax at the rate of
the receipt. If there is no input tax code for this rate — usually foreign VAT,
which is not deductible input tax —, the proposal stops: record the expense gross
without a tax rate. Invoices with reverse charge belong in incoming invoices, which
know the tax case.
