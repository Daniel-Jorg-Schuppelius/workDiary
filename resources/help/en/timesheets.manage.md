---
title: "Managing timesheets"
topic: timesheets.manage
version: 4
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

**Material from "Today":** Via "Material" on "Today" you record consumables with project, quantity and unit — also without a network. They go onto your open timesheet of the project for that day; if there is none (or it is already signed), a new one is created.

**Stock withdrawal:** If the material is linked to a stock-managed item (item marked “Stockable”) and the inventory module is active, the usage posts the withdrawal from the organisation's default warehouse — in the unit entered and valued with the warehouse's valuation method. If you change the quantity, only the difference is posted; another material returns the previous item, and deleting the line or the whole timesheet returns the withdrawal. Below the description the line shows whether and from which warehouse stock was posted. If the stock is insufficient, the entry is rejected; with the “Approve negative stock” permission you select “Allow negative stock”. If the organisation requires four eyes for this, the withdrawal waits for approval in the approval inbox (“Negative stock requested”). Withdraw items requiring serial numbers via the warehouse; items requiring batches are taken from batches, earliest best-before date first. Without a default warehouse the line shows “Not posted – no default warehouse”; if stock is kept read-only in another system, WorkDiary does not post. If another system manages the stock, every movement is also sent there. The same applies offline: if stock prevents the posting, the entry ends up as a conflict under “Offline changes”.

**Sum limit:** Material you record on “Today” lands on the open timesheet of the day. If quantity × unit price together with the other lines would exceed the timesheet’s storable total, the entry is rejected — online and offline.
