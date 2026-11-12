---
title: "Entry type analysis"
topic: reports.entry-type-analysis
version: 4
keywords:
    - plan vs actual
    - target vs actual
    - processing time
    - average duration
    - rework rate
    - effort overrun
    - compare job types
    - root causes
    - variance analysis
    - estimate accuracy
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

The entry type analysis compares planned and actual durations per entry
type (e.g. maintenance, incident, migration) and shows:

- **average processing time** and spread
- **plan/actual deviation** in percent
- **rework rate**
- **top causes** (if maintained as classifications)

This reveals entry types where planning assumptions are unrealistic or
the team systematically needs extra effort – both pointing at training
or pricing needs.

An order's planned duration is its **Planned duration (HH:MM)**; if it is
empty, the service duration of a dispatched order, otherwise the length of the time slot or the duration of the appointment counts.

The **Top causes** column lists, per order type, the three most frequent classifications from the rework reason of booked times and the root cause of complaints about the order. Each order counts once; a click shows the affected times and complaints. The CSV export contains the column as well.
