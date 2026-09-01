---
title: "Passenger transport (taxi/private hire)"
topic: passenger.overview
version: 1
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
