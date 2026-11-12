---
title: "Meter-based billing"
topic: metering.billing
version: 1
keywords:
    - usage-based billing
    - metered billing
    - pay per use
    - click charge
    - cost per copy
    - copier billing
    - free allowance
    - tiered pricing
    - base fee
    - operating hours billing
    - consumption billing
audience: []
related:
    - invoices.manage
    - assets.fleet
    - contacts.manage
---

Meter-based billing invoices consumption-dependent contracts — copies on a
multifunction device, operating hours, kWh.

**Agreement:** Each meter defines which customer is billed, in which cycle,
with which base fee, free allowance and unit price. Tiered prices are
possible; the tier applies to the period's consumption.

**Consumption:** What is billed is the difference between the last reading
*before* the period and the last reading *within* it — not the sum of the
recorded consumption figures, which would include the time before. If a
reading is missing, the period is not billed rather than estimated.

**Run:** The monthly run creates draft invoices, not sent ones — the
approval stays with a person. Each period is billed only once; a second run
produces no duplicate.

## Tiers, status and project

**Volume tiers:** The agreement dialog offers rows with **from quantity** and
**price per unit**. The first tier starts at 0; empty rows are ignored. With
tiers, each tier's price applies instead of the unit price, and the
calculation is progressive on the consumption after deducting the free units —
one more unit never makes the invoice cheaper. The list then shows “Tiered”
instead of the unit price.

**Status:** *Active* is billed; the run skips *Paused* and *Ended*, and **Bill
now** is only offered for active agreements. After a pause the run catches up
from the **Next billing** date — if the paused time should not be billed, move
the date forward before reactivating.

**Project:** A selected project is assigned to the draft invoices of the run.
