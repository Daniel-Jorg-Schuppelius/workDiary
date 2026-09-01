---
title: "Working-time compliance"
topic: reports.arbzg-compliance
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.overview
    - reports.drilldown
---

The working-time compliance report checks the **actually recorded working time**
(clock-ins/attendances, net of breaks) per employee and day against the
thresholds of the German Working Hours Act (ArbZG). It is the actuals view — the
plan compliance of the duty roster is unaffected.

It checks:

- **Maximum daily hours** – violation when a day's net working time exceeds the
  daily limit (default 10 h, ArbZG §3).
- **Rest period** – violation when less than the minimum rest period (default
  11 h, ArbZG §5) lies between the end of one working day and the start of the
  next.
- **Mandatory break** – violation when the recorded breaks fall below the
  statutory minimum (ArbZG §4: 30 min over 6 h, 45 min over 9 h).
- **Maximum weekly hours** – notice when the weekly total exceeds the average
  limit (default 48 h, ArbZG §3).

The thresholds come from the organisation's compliance settings and are
identical to the day closure and duty-roster checks.

Each entry links via **Open day closure** to the affected day. If an approved
time correction exists for a day, the entry is flagged **corrected**. The list
can be exported as CSV or PDF.
