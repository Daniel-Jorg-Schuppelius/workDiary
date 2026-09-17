---
title: "Travel logs, expenses & allowances"
topic: travel-expenses.manage
version: 1
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

Travel logs, expenses and meal allowances document business travel as
separate records connected by period and receipt context.

Typical flow:

1. Record date, route, purpose, vehicle and odometer values.
2. Add expenses with category, amount, payment method and receipt.
3. For multi-day travel, calculate allowances from travel times and
   destination.
4. Review the data and submit it for approval or accounting.

Receipts, odometer values and travel times must be plausible. Approved
or settled records must not be changed silently; corrections need a
traceable workflow.

## Pushing an expense to accounting as a voucher

An **approved** expense can be pushed directly from the receipt dialog to the
leading accounting system as a purchase voucher — instead of entering it there
a second time. The external voucher ID comes back on creation; the duplicate
cannot arise in the first place.

Three rules:

- **Approved expenses only.** The push is irrevocable — the target system
  knows neither update nor delete for vouchers. Corrections run there as a
  counter-voucher.
- **No push without a posting category.** The mapping is maintained per
  expense category (Administration → Expense categories); a guessed category
  would be worse than the error message.
- **From the push on, the voucher leads.** The link can no longer be removed —
  the voucher exists, linked or not.

The expense's receipt files are uploaded along — without a file the voucher is
worthless to accounting.

### Correcting with a counter voucher

If something is wrong with an expense that was already pushed, correct it in the
receipt dialog **with a counter voucher** – a reason is required. A purchase
credit note for the same amount is pushed, cancelling the original voucher in
accounting. At the same time a new expense is created as a **draft** that refers
to the old one; it goes through approval and pushing like any other.

If the original expense was approved but not yet reimbursed, it is cancelled –
otherwise both would be paid out. If it was already reimbursed, the draft points
out that only the difference must be reimbursed.

## Scan a receipt instead of typing it

Instead of entering amount, date and merchant by hand, you can **photograph
the receipt or upload it as a PDF**. Recognition reads the usual fields and
pre-fills the form.

The result is a **suggestion**, not a finished entry: check amount, date, tax
rate and merchant before saving. Poorly lit photos, thermal paper and
handwritten receipts are the most common sources of misreadings.

The original receipt stays attached to the record unchanged — recognition does
not replace it, it only saves you the typing.
