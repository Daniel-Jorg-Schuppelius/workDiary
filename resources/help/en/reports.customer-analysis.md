---
title: "Customer analysis"
topic: reports.customer-analysis
version: 2
keywords:
    - customer report
    - customer statistics
    - orders per customer
    - time per customer
    - rework rate
    - non-billable time
    - margin risk
    - customer KPIs
    - client analysis
    - customers & projects
    - hours per project
    - revenue per customer
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.drilldown
    - reports.entry-type-analysis
    - reports.billing
---

The **customer analysis** shows effort, rework and non-billable time per
customer in the period from the page header; it requires the "View
reports" permission. **Customers & projects** breaks down hours and
revenue per customer and project.

## Customer analysis

Columns of the table:

- **Orders** in the period, **Total** (booked minutes), **Billable**,
  **Non-billable** and their **Share %** – a high share is an early
  indicator of margin risks.
- **Rework**: orders with a defect protocol in the period.
- **Open issues** on the customer, its orders and projects, of which
  **Escalated** (blocked).
- **Ø min/order** and **30d trend**: orders of the last 30 days minus
  the 30 days before.

Filters: **Project**, **Employee** and **Order type**, plus **Minimum
effort (minutes)**, **Hide customers without values** and **Include
hidden customers** (customers marked "Hide in reports").

Click targets: the customer name opens the customer file, **Orders** and
**Total** open the diary for the period. **Rework**, **Open issues** and
**Escalated** open the drilldown with the same filters. Above the table
you find **Hours per customer (top 20)** – a click opens Customers &
projects for that customer –, incoming orders over time, **Open issues
per customer (top 15)** and the lists **Top 5 effort**, **Top 5 rework**
and **Top 5 non-billable**. Export as PDF, CSV or Excel.

## Customers & projects

The **Customer / project** table shows the total per customer and below
it the projects with **Hours** and **Revenue**, projects sorted by hours
in descending order; **Σ hrs** and **Σ €** appear above. Revenue is the
sum of the stored amounts of the time entries. The charts show **Hours
per customer (top 20)** – a click filters the page to that customer –
and **Top projects by hours**.

Filters: **Customer**, **Project** and **Include hidden customers**.
Whoever may see all time entries chooses under **Area** between **Only
mine** and **Entire team** and filters by **Employee**; everyone else
sees their own times. Export as PDF, CSV or Excel.
