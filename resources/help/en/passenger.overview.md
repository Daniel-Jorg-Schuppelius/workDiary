---
title: "Passenger transport (taxi/private hire)"
topic: passenger.overview
version: 3
keywords:
    - taxi company
    - private hire
    - chauffeur service
    - dispatch
    - trip record
    - taximeter
    - operator license
    - fare
    - fixed price
    - shift settlement
    - driver settlement
    - demand-responsive transport
audience: []
modules:
    - module.fuhrpark
related:
    - claims.overview
---

The taxi/private-hire industry profile keeps every passenger trip as its own
ride file: acceptance with a frozen operating mode, dispatch with mandatory
checks (passenger transport licence, concession, vehicle proofs), trip start
with a frozen tariff or fixed price, and completion with the meter value,
tax decision and payment method.

**Rides:** New rides are created via "New ride". Private-hire and pooled
on-demand transport require documented order receipt at the business seat;
only taxis may have open destinations. Dispatch validates driver, vehicle
profile and concession — obstacles are shown as validation errors.

**Master data:** Tariffs are versioned (validity period, base, per-km and
per-minute prices, surcharges, fixed-price corridor). Concessions and
vehicle profiles with calibration, BOKraft and inspection dates live next to
them; expired proofs block dispatch.

**Shift settlement:** Meter revenue and payment kinds (cash, card, voucher,
invoice, mediator) are kept separate; tips do not count against the meter
total. Differences stay open until they are resolved with a reason.

WorkDiary replaces neither the taximeter/odometer nor the TSE — those systems
remain authoritative; their values are documented and reconciled.

**Waiting time when completing a ride:** When completing a ride you enter the waiting time in minutes; it is prefilled with the time between start of waiting and pickup. It is stored to the second.

## Rides in the shift settlement

When a shift settlement is closed, WorkDiary binds the driver's finished rides from that shift (shift times, otherwise the shift day): completed rides count as settled, cancelled, aborted and no-show rides as done without an amount. The ride then shows its settlement.
