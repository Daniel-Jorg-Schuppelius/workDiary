---
title: "Working-time compliance"
topic: reports.arbzg-compliance
version: 1
keywords:
    - Working Hours Act
    - working time violations
    - maximum working hours
    - rest period
    - 11-hour rest
    - mandatory break
    - break rules
    - young workers
    - night work
    - minimum wage act
    - record-keeping obligation
    - labor law check
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

**Night time and young workers:** You set the night time (default 11 pm to
6 am, 10 pm to 5 am in bakeries) in the compliance settings; the § 3 average
no longer counts public holidays as working days. If a date of birth is stored
for the employee, the report also checks the days before their 18th birthday
against the German Youth Employment Protection Act: at most 8 h a day and 40 h
a week, rest breaks (30 min from 4.5 h, 60 min from 6 h), 12 h time off, no
work between 8 pm and 6 am, at most 5 working days a week. Weekend work and
night work from age 16 appear as notes, because the act allows exceptions by
industry.

The minimum wage recording deadline (seven days) is measured from the original
recording: for clockings the moment of clocking, even if a device transmits
later while offline; for imports the column “erfasst am” (recorded at). Imports
without this information are not checked against the deadline.
