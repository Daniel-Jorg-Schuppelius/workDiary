---
title: "Special terms & billing account"
topic: customers.billing
version: 3
audience: []
modules:
    - module.vertrieb
related:
    - contacts.manage
    - invoices.manage
    - customer-portal.billing
---

On the customer file you can configure **special terms**: custom hourly
rates per activity and day type (weekday/weekend, defined via "working
days per week") plus the billing method — an invoice-less **customer
account** with a running balance, a **monthly invoice** or a **retainer
(Lexoffice)**.

The terms also cover a **travel flat rate**: every billable time entry
then carries an extra x minutes, valued at the entry's rate — optionally
only for selected activities. Recorded working time stays untouched, so
working-time accounts and flexitime are unaffected; the statement and the
PDF show travel in its own column. On the time entry the value can be
overridden for a single case (including 0). Travel and standby entries as
well as fixed-price entries never receive a travel flat rate.

Whether a day counts as weekend is defined by "working days per week"
(6 = Sunday only). Optionally **public holidays** count as weekend too,
taken from the organisation's holiday calendar. The calendar day of the
start time decides — an entry running past midnight belongs entirely to
its starting day.

In account mode each month gets a billing block: total (hours × rate),
settled (payments), previous month (carry-over) and outstanding
(balance). The balance carries into the following month automatically.
Months are **closed** chronologically (lock + snapshot, times count as
settled) and can be reopened in reverse order if needed.

Record payments manually on the panel or through bank transaction
reconciliation (the customer account is an allocation target). Late
entries in closed months are flagged — reopen the month or re-date the
entry.

In **retainer mode** Lexoffice owns the document and the payment. The
monthly retainer is stored net ("expected monthly amount"); the local
balance puts hours × rate against the retainer paid. There are two ways
to get the document:

- **Send retainer** creates the invoice in Lexoffice (also monthly and
  automatically for the previous month).
- **Link document** attaches an invoice you already created in
  Lexoffice. If exactly one customer invoice matches by month and net
  amount, this happens automatically during the document sync.

Once a document is attached, "Send retainer" disappears — otherwise a
second document would appear in Lexoffice. The payment status flows back
during the document sync and is booked **net** (Lexoffice runs gross).

If special terms were only added later, older times start out at 0.00 €
under "total". **Recalculate** values them with the configured rates;
manually overridden rates stay untouched.

The customer sees attendance and balance in the customer portal under
"Billing" and can download the attendance record as a PDF.
