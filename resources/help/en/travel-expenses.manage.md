---
title: "Travel logs, expenses & allowances"
topic: travel-expenses.manage
version: 2
keywords:
    - travel expenses
    - expense report
    - expense claim
    - mileage log
    - mileage allowance
    - per diem
    - meal allowance
    - scan receipt
    - reimburse expenses
    - company car
    - 1 percent rule
    - driving times
    - trip log
    - record a trip
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

## Recording a trip

You add a new trip via **New …** in the sidebar: in the **Planning** group,
**Trip log** opens the **Record new trip** dialog. All recorded trips are
listed under **Trips & expenses** → **Logbook**.

- **Trip:** **Date**, **Vehicle** (vehicle type with its rate per kilometre),
  **Fleet vehicle (optional)**, **Trip type** as well as **From (address)** and
  **To (address)**.
- **Distance & rate:** **Distance (km, one-way)** is required. If **Rate €/km
  (optional)** stays empty, the rate of the fleet vehicle applies, otherwise
  that of the vehicle type. Add **Odometer at start (km)**, **Odometer at end
  (km)**, **Start (time)** and **End (time)**; if the trip ends after midnight,
  simply enter the smaller time.
- **Assignment:** **Project (optional)**, **Customer (optional)** and
  **Purpose**.
- **Options & notes:** **Round trip (doubles km)**, **Reimbursable**
  (preselected) and **Notes**.

**Capture** saves the trip in your name and returns to the list. If start and
end are filled in, WorkDiary by default creates a non-billable time entry for
the travel time. A higher odometer reading at the end is copied to the fleet
vehicle. If the fleet vehicle is in **Logbook mode**, odometer readings are
required and the trip is locked after the end of the day. Anyone signed in can
record trips as long as your organization uses the module; a trip always
belongs to the person who recorded it.

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

## Logbook: signature, correction and 1% comparison

In logbook mode the driver completes a trip **with their signature**; the trip is
locked afterwards. A trip already locked at the end of the day can still be
signed. If a trip in the middle of the chain is corrected by a reversal trip and
its end odometer reading changes, the next trip automatically starts there — as a
follow-up correction, the original remains.

The **1% comparison** below the logbook report compares the logbook method with
the 1% rule per vehicle and year. The vehicle needs the gross list price and the
home–work distance; you enter other annual costs (leasing, insurance, tax) there,
energy comes from the fuel and charging receipts. Optionally, a setting blocks new
trips while a vehicle's mandatory inspection is overdue.

**Driving and rest times.** If your organisation applies the driving time
rules, enter a second driver on the trip (multi-manning) or mark crossings
where the vehicle travels on a ferry or train. The second driver gets no
driving time, but their time in the vehicle does not count as rest; in
multi-manning 9 hours of rest within 30 hours are enough. A ferry or train
crossing does not interrupt the rest.
