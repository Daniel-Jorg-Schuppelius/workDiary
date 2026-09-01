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
