---
title: "Managing projects"
topic: projects.manage
version: 3
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - time-entries.start
    - timesheets.manage
    - finance.transfers
---

## Purpose and background

Projects bundle everything that belongs to an undertaking: customer,
duration, responsibilities, tasks, milestones, booked times and the
billing rules. They are the bracket between time tracking and
invoicing — whatever is set correctly on the project never needs
fixing per booking later.

## Requirements

- An existing customer (see customers & suppliers).
- The right to manage projects.
- For billing: clarified billing rules (hourly rate, flat fees,
  billable yes/no).

## Recommended workflow

1. Create the project with **customer and period**.
2. Set **responsibilities and status**.
3. Plan **tasks or recurrences**.
4. Book work and check progress in the detail view.
5. Before closing, check open tasks, times, timesheets and billable
   positions — only then close.

![Project list with customer, status and duration](media/kunden/projektliste.png)
*The project list: every project with customer, status and duration.*

The **Times** tab above the project list shows the time entries of all
projects in the period selected at the top, without opening each project.
Entries are grouped by project; use “Group by” to switch to date or person.
Each group states the number of entries and the total for the whole period
and can be collapsed. You can filter by search term (project, task,
description), customer, project, employee, tag and billability. Only
administration, accounting and people allowed to view all times see other
people’s entries; everyone else sees their own. Time entries without a
project are not listed here.

The same visibility rule applies on a single project (time tracking,
timesheets, total hours), in the case file of an order and to the time
figures on the customer page: without access to all times, lists and
totals count only your own entries and carry the note “own times only”.

## Practical example

For a server migration the project "Migration DC" is created with
duration, hourly rate and two responsible people. Technicians book
their time directly onto the project; at the end of the month the
detail view shows at a glance what is billable and open.

## Common mistakes

- **Closing too early:** a closed project accepts no more bookings —
  check open times and positions first.
- **Changing billing rules retroactively** and expecting old bookings
  to follow: rules apply to future processes.
- **Booking everything without a project:** without a project link,
  reporting and a clean billing handover are missing later.

## Effects and next steps

Billing rules and project status determine which times and materials
go into the handover. Next: set up time tracking on the project and
check the billing handover at the end of the period.
