---
title: "Service plan per customer and period"
topic: service-plan.overview
version: 1
keywords:
    - service plan
    - annual list
    - monthly list
    - scope of services
    - not required
    - documents received
    - my periods
audience: []
related:
    - customers.deadlines
    - diary-entries.follow-up
    - diary-entries.review
    - service-plan.import
---

The **service plan** shows which recurring services your customers receive
and how far along they are — without maintaining a second list. The status
comes from orders with a **period**; which services a customer has is set in
the customer's **scope of services**.

## Two views

- **Year view:** rows are customers with an annual service, columns are the
  services (e.g. annual accounts, income tax). Choose the columns via
  **Services** in the filter bar; under **Saved filters** you keep the
  selection as a view. “Previous year” and “current year” move along in saved
  views.
- **Period view:** rows are customer × service per month, quarter or
  half-year, columns are the twelve months. A quarter spans three months, a
  half-year six. In front of them you see the traits of the customer deadline
  rule set (e.g. VAT interval, permanent extension, care grade).

## What a cell shows

- If there is an order for the period, the cell shows its status: created,
  accepted, in progress, waiting, in review, done.
- Without an order a **mandatory service** shows “missing”; an **optional
  service** stays quiet.
- Mark **not required** with a reason as long as there is no order.
- The **due date** comes from the order, otherwise from the matching customer
  deadline; within the year finally from the end of the period. Anything past
  it is marked red.
- Every cell keeps **documents received on** and a **remark**; where the rule
  set knows it, **requested early on** is shown too.

Clicking a cell opens it: **Create order** prefills the order dialog with
customer, entry type, product group, period, responsibility and due date; if
the order exists, open it from there.

## Count tiles and filters

The tiles count cells (missing, open, in review, done, not required, overdue)
and filter to customers with such cells. Further filters: responsible person,
customer group, search by name or customer number.

## Create the year's periods

In the period view, **Create the year's periods** creates the orders of all
periods for one service — each as the follow-up of the previous one, with
responsibility, review requirement, procedure and notes. Existing orders and
“not required” stay; customers without a predecessor get their first order via
the cell.

## Responsibility and deputies

The responsible person is the one on the order, without an order the person
responsible for the deadline traits. **Mine** also shows the cells of the
people you are deputising for today. Without the right “View service plan” you
only see these cells. The **My periods** card on the dashboard and the work
queue (“Overdue periods”) show what is open.

## Service templates

Industry profiles bring **service templates** (e.g. tax returns and annual
accounts, monthly support, quality control, counselling visit). When you
create a customer, the dialog offers them as the scope of services.
