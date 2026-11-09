---
title: "Special terms & billing account"
topic: customers.billing
version: 5
keywords:
    - special prices
    - customer hourly rate
    - weekend rate
    - holiday rate
    - travel flat rate
    - call-out fee
    - customer account
    - balance
    - monthly retainer
    - flat fee
    - Lexoffice
    - sevDesk
    - easybill
    - month-end closing
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
account** with a running balance, a **monthly invoice** or a **retainer**
via the accounting system (Lexoffice, sevDesk or easybill).

The terms also cover a **travel flat rate**: every billable time entry
then carries an extra x minutes, valued at the entry's rate — optionally
only for selected activities. Recorded working time stays untouched, so
working-time accounts and flexitime are unaffected; the statement and the
PDF show travel in its own column as long as **Show travel separately** is ticked; without the tick, the travel is included in the duration in the portal and the statement, the amount stays the same. On the time entry the value can be
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

In **retainer mode** the accounting system of the customer's billing
authority owns the document and the payment: Lexoffice, sevDesk or
easybill. The monthly retainer is stored net ("expected monthly amount");
the local balance puts hours × rate against the retainer paid. There are
two ways to get the document:

- **Send retainer** creates the invoice in the accounting system (also
  monthly and automatically for the previous month). sevDesk assigns the
  invoice number and then keeps the invoice as sent and open; easybill
  finalises and numbers it.
- **Link document** attaches an invoice you already created in the
  accounting system. If exactly one customer invoice matches by month and
  net amount, this happens automatically during the document sync.

Once a document is attached, "Send retainer" disappears — otherwise a
second document would appear in the accounting system. The payment status
flows back during the document sync and is booked **net** (the systems
keep the open amount gross); partial payments count proportionally. For
sevDesk and easybill the payment status comes from the document mirror,
i.e. from the regular document fetch; a cancelled document, or one deleted
in the system, reverses the booked payment. If sending to sevDesk or easybill is
interrupted, the invoice stays as a draft; sending again continues it instead of creating a
second document in the system. Payments from the accounting system cannot
be voided by hand in the panel — that happens via the document in the
system.

If special terms were only added later, older times start out at 0.00 €
under "total". **Recalculate** values them with the configured rates;
manually overridden rates stay untouched.

The customer sees attendance and balance in the customer portal under
"Billing" and can download the attendance record as a PDF.
