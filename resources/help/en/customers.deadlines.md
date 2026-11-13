---
title: "Customer deadlines"
topic: customers.deadlines
version: 1
keywords:
    - Customer deadlines
    - Deadlines
    - Client deadlines
    - Advance VAT return
    - Permanent filing extension
    - Wage tax return
    - EC sales list
    - Annual tax return
    - Contribution statement
    - Counselling visit
    - Service scope
audience: []
related:
    - contacts.manage
    - work-queue.overview
    - delegations.manage
    - accounting.filings
---

Some deadlines do not depend on a contract but on a customer's
characteristics and on the law: the advance VAT return of a tax firm's client,
the social security contribution statement of a payroll client, the
counselling visit of a care client. workDiary calculates these **customer
deadlines** from the customer's deadline traits and service scope, reminds in
good time and ticks them off as soon as the matching order is completed. The
interface says “customer” – in a tax firm that is the client (in workDiary
“tenant” means the organisation).

## Rule sets

A **rule set** contains the statutory rules of an industry. It is enabled when
the industry profile brings it along or when you enable it under **Customer
deadlines › Settings**.

- **Tax advisory:** advance VAT return (10th day after the period, one month
  later with a permanent filing extension), special VAT prepayment (10 February
  for monthly filers with an extension), wage tax return (10th day, never
  extended), EC sales list (25th day, never extended), annual returns per type
  of tax from the service scope (advised: end of February of the second
  following year, otherwise 31 July; transitional deadlines up to 2024) and –
  with the **payroll** service – the social security contribution statement
  and due date (third-last bank working day, statement two bank working days
  earlier). If a tax deadline falls on a weekend or a holiday **at the tax
  office's location**, the next working day applies; with several health
  insurers the earliest date of all federal states applies.
- **Home care:** counselling visit under § 37(3) SGB XI – mandatory only with
  care allowance and care grade 2 to 5, once per half-year; with care grade 4
  and 5 on request once per quarter. workDiary reads the care grade from the
  customer's classification.

## Maintaining deadline traits

In the **customer cockpit**, the **Customer deadlines** card shows the traits
and the next open deadlines. **Deadline traits** opens the dialog, prefilled
like the current version (“as last year”). A change applies from the chosen day
and closes the previous version – past deadlines keep their basis. workDiary
then recalculates only **open** deadlines; done, extended and
requested-in-advance ones stay as they are. Nothing due before the start of
the first version is created.

If the **federal state of the tax office** or of the collecting agencies is
missing, workDiary uses the organisation's holiday region and flags the
deadline.

**Service scope** lists the customer's services (entry type and product group,
mandatory or optional, period type, valid from/until). The tax advisory rule
set reads the annual returns and the payroll service from it; the service plan
shows them per period.

## Working on deadlines

Clicking a deadline opens it with its history. You can mark it **done**,
**extend** it (new date and reason), flag it as **requested in advance** (the
tax office wants the return earlier, § 149(4) AO), **waive** it or **reopen**
it. A completed order with a period completes the matching deadline by itself –
for example a **counselling visit** order for 2026-H1 the visit of the first
half-year, or a **payroll** order for March the wage tax return and the
contribution dates of March.

## Overview, reminders and deputies

- **Customer deadlines** lists all deadlines with a traffic light (overdue,
  within advance warning, on track), filters by rule set, deadline type,
  responsibility, month and status, and export as CSV or Excel.
- The **responsible person** receives one bundled reminder when deadlines
  enter the advance warning period (default 7 days, adjustable per rule set)
  and another one when they become overdue. Deadlines up to the weekend and
  overdue ones appear in their **work queue** and **calendar**.
- During a **deputyship**, the deputy sees these deadlines and reminders marked
  “On behalf of …”. On departure or final handover, responsibility passes to
  the successor.

People with the permission **View customer deadlines** may see customer
deadlines; traits, service scope and all deadlines are maintained by those who
have **Manage customer deadlines and deadline traits**. The responsible person
also works on their own deadlines with the view permission.

## What workDiary does not do

It does not submit anything to ELSTER, DATEV or care insurers and does not keep
books for customers. Agreed deadlines remain contract obligations, deadlines on
equipment remain inspection plans.
