---
title: "Managing projects"
topic: projects.manage
version: 2
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
