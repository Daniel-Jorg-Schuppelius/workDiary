---
title: "Assets & fleet"
topic: assets.fleet
version: 9
keywords:
    - fleet management
    - vehicle management
    - inventory
    - equipment
    - check out equipment
    - tool loan
    - equipment return
    - fuel log
    - charging log
    - maintenance schedule
    - report defect
    - asset lifecycle
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Assets and vehicles represent operational objects with status,
responsibility, documents and maintenance information. Fuel and
charging logs add consumption history.

Maintain master data and unique identifiers, assign locations or
responsible people, and record maintenance intervals and relevant
documents. Status changes should reflect the real lifecycle.

Before deletion or retirement, check for linked maintenance, work
items, journeys or documents. Preserve critical history through
archiving instead of overwriting it.

## Categories

The "Category" field offers the general IT categories and your organisation's
categories. Industry profiles bring suitable categories along, such as pipe
inspection camera and press tool for HVAC or pulse oximeter and controlled
drugs cabinet in care. Administrators create their own categories under
Classifications in the "Asset categories" domain. A deactivated category is no
longer offered for new assets but stays on assets that already carry it.

## Check-out and return

The "Check-out / return" panel on the asset detail page hands a device
to a person or team, optionally with a work-order reference and an
expected return date. An asset has at most one open assignment; an asset
that is already checked out or blocked by a defect cannot be checked out
again. Returning it makes the asset available again. If an assignment
passes its expected return, an overdue hint appears and the deadline
scanner notifies the borrower or the team lead.

## Defects and blocks

The "Defects / blocks" panel records faults with a severity. When "block
asset" is set, the open defect blocks any further check-out until it is
resolved or written off. Resolving or writing off a defect requires a
resolution note.

## Maintenance plans

In the **Maintenance plans** card, **Create plan** sets up recurring
maintenance: **Name**, **Interval** with **Value**, **Tolerance (days)**
and **First due date**. For days, weeks and months, **Done** calculates
the next due date from the interval; for operating hours and kilometers
you enter it yourself. **Pause** takes the plan out of the due check.

Whoever may view SLA contracts binds the plan to an **SLA contract** – a
contract of the asset's customer or one without a customer – and sets
**Contractual obligation** if the plan represents a mandatory appointment
of that contract. The plan then appears on the contract under
**Contractually required maintenance**; the marker is also shown on the
asset, in the object file and in the customer portal. If the helpdesk is
enabled, **On due date** defines what the daily due check does: **Create
a service ticket** creates exactly one ticket per due date with the
selected contract, **Notice only (no record)** merely notes the due
date.

## SLA contract on the asset

Whoever may view SLA contracts selects an **SLA contract** for the asset under
**Contracts & SLA** in the **Edit** form – a contract of the saved customer or
one without a customer. OLA, project-bound and inactive contracts are not
offered. The contract applies to service tickets for this asset ahead of the
project and customer contracts (topic "SLA, contracts & service levels"); it is
shown in the header of the detail page and in the object file as **Direct
assignment**. Without the right to view SLA contracts the field is missing and
the binding stays unchanged when saving.

## Object file (lifecycle)

The "object file" consolidates an asset's entire lifecycle into one
coherent, printable view: master data, location and room, the derived
lifecycle status (in operation, replaced or decommissioned),
commissioning, decommissioning and warranty. Below it lists maintenance,
check-outs and returns, defects and blocks, linked orders, protocols,
material usage, open issues and attachments, plus the full lifecycle
history.

Open it via the "Object file" button on the asset detail page; use the
browser print function to produce a document (appending "?print=1" opens
the print dialog directly). The lifecycle status is derived from status,
decommissioning and warranty – there is no separate field to maintain.

## Decommissioning

**Decommission** on the asset detail page takes an asset out of operation for
good. Date and reason are mandatory; both then appear in the header of the
detail page and in the object file. Take back an asset that is still checked
out first. The step pauses the maintenance plans, suspends the inspection
duties and cancels open inspection dates. Afterwards the asset can no longer
be checked out, rented, reserved or used in a procedure, and it no longer
appears in the selection lists for rental and print orders. The file with all
records is kept. The step cannot be undone; the status "Out of service" is
not offered in the "Edit" form.

**Permission:** **Decommission assets/objects**.

## Change of owner

**Change owner** on the asset detail page records a change of ownership
(organisation, customer, external) and customer, with a note if you wish.
Every change appears in the object file under "Ownership change history" with date,
from, to, person and note; the entries cannot be changed. Choosing a
different customer in the "Edit" form is the same change: it is recorded
with the note entered there.

If the customer changes, bindings to contracts of the previous customer are
removed – on the asset and on its maintenance plans, there together with
**Contractual obligation**; contracts without a customer remain. Select a
contract of the new customer after saving, in the form opened again.

**Permission:** **Transfer ownership of assets** – also for changing the
customer in the "Edit" form.

## Status, block and condition

The statuses **Locked** and **In repair** block an asset wherever a block
from inspections or rental applies as well: check-out, rental, vehicle
reservation, print order and the start of a procedure on the asset or on an
order for this asset. A procedure template that explicitly allows blocked
assets (such as the repair) still starts. For this the status creates a block
with the reason "Manually blocked" or "Maintenance". It ends when you reset
the status or lift the block under **Test equipment**; the asset is then
**Active** again. Temporary exception releases apply to this block as to any
other.

The **condition** next to the status is derived and not maintained by hand:
**Critical** with an active block or a blocking defect, **Degraded** with an
open defect or overdue maintenance or inspection, otherwise **OK**. It is
updated with every change and at night.

If you change the free-text location or the room, the change appears in the
timeline as "Location changed" with the old and the new location.

## Return with condition

**Withdraw** opens a dialog for **Condition on return** and **Note**. Both
then appear in the history table of the "Check-out / return" panel next to
the condition at check-out, and in the object file.

## Cockpit

Below the sections of the detail page, the modules show what exists for the asset: open **service tickets**, **leasing and financing** (contracts the asset belongs to), open **complaints**, open **warranty periods** from orders and protocols for the asset, **open issues** and **procedures**. A panel appears only if the module is enabled, you have the right to the corresponding list and there are entries; clicking opens the entry.

**Return to service:** You return a decommissioned asset to service with the action of the same name — with a reason and an audit log entry, using the same permission as decommissioning. Paused maintenance plans and dormant inspection duties stay off and are resumed one by one. An asset disposed of through a disposal job remains decommissioned; the edit form does not change this status.

## Parts list

The **parts list** of a device records what is installed in this unit: article
or free text, quantity, position, serial number, installation date and
replacement interval. Replacing creates a new part and keeps the old one with
its removal date in the history; due wear parts appear as a suggestion. Under
**Material from jobs** you find the material usages assigned to this device on
a timesheet. **Adopt** adds a material to the parts list with the job date as
installation date – with the article if the material has one; each usage can
be adopted only once.
