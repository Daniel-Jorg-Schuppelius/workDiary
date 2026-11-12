---
title: "Managing timesheets"
topic: timesheets.manage
version: 3
keywords:
    - timesheet
    - time report
    - proof of work
    - service report
    - work report
    - hours sheet
    - get signature
    - customer sign-off
    - record materials
    - bill services
    - timesheet PDF
audience: []
related:
    - time-entries.start
    - protocols.sign
    - finance.transfers
---

Timesheets combine project or period services for review, signature and
further billing. Lines may contain time, activities and materials.

Typical flow: select the project, add lines, review totals, provide the
timesheet for signature and complete it after confirmation. Signed
content must not be changed silently; corrections require a traceable
new state.

Before release, verify period, customer, project, service description
and quantities. A PDF is an output of the stored state, not the primary
data source.

**Material from "Today":** Via "Material" on "Today" you record consumables with project, quantity and unit — also without a network. They go onto your open timesheet of the project for that day; if there is none (or it is already signed), a new one is created. Material on a timesheet does not post a stock withdrawal.

**Sum limit:** Material you record on “Today” lands on the open timesheet of the day. If quantity × unit price together with the other lines would exceed the timesheet’s storable total, the entry is rejected — online and offline.
