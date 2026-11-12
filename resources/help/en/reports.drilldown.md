---
title: "Drilldown from the KPI to the individual cases"
topic: reports.drilldown
version: 3
keywords:
    - drill down
    - drill-through
    - KPI details
    - break down a metric
    - underlying data
    - show source records
    - detail list
    - open issues per customer
    - defect protocols
    - repeat defects
    - causes
    - export report
    - trace figures
audience: []
related:
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.operations
---

In the **customer analysis**, the **order-type analysis** and the
**product analysis**, figures are clickable. The drilldown lists the
individual cases behind the figure – with the period from the page
header and the filters that were active when you clicked. The header
names the customer, order type or scope and the period; **To customer
analysis**, **To order-type analysis** or **To product analysis** takes
you back. The pages require the "View reports" permission.

## Open issues

**Drilldown: open issues** shows the issues that are still open – on the
customer itself, on its orders and projects, on the orders of an order
type or on the assets of the selected scope (then with the **Asset**
column). Columns: **Title**, **Status**, **Severity**, **Due** and
**Assigned**; the most recently changed issues are at the top. Clicking
the **Escalated** column opens the list with **Escalated only**: only
blocked issues appear then.

## Defect protocols

**Drilldown: defect protocols** lists the defect protocols on orders in
the period; in the customer analysis it sits behind the **Rework**
column. Columns: **Title**, **Status**, **Time**, **Created by** and
**Order** – a click opens the order. The newest protocols are at the
top.

## Causes and repeat defects

**Drilldown: cause (order type)** shows two lists for a cause from the
order-type analysis: **Rework with this reason** (date, order, employee,
minutes) and **Complaints with this root cause** (number, order,
status).

**Drill-down: repeat defects** shows per asset the defect reports in the
period and in the last **12 months** as well as the severity levels. From
three defects within twelve months the row carries the **Repeat defect**
marker; the asset name opens the asset dossier. You reach it via the number
in the **Repeat defects** column of the product analysis: it then shows only
the repeat-defect cases of the clicked row, and the header names the area
and **Repeat defects only**.

## Export

You export open issues and defect protocols as PDF, CSV or Excel, repeat
defects as CSV or Excel. The export contains the same rows as the list
and states the period and filters. The cause page has no export.
