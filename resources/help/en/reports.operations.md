---
title: "Operations: time allocation, procedures, material, on-call"
topic: reports.operations
version: 1
keywords:
    - operations report
    - service orders
    - cost center report
    - procedure deviations
    - blocked procedure runs
    - material consumption
    - standby duty
    - defect rate
    - project hours
    - archive projects
    - missing classification
    - tour report
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - reports.overview
    - reports.drilldown
    - procedures.run
    - materials.manage
    - duties.overview
    - projects.manage
    - admin.time-dimensions
    - admin.classifications
---

These reports show what happens in day-to-day operations: service orders,
tasks and tours, how working time is allocated to projects, cost centers and
further dimensions, deviations and blocks in procedures, consumed material,
on-call duties, defects on products, and the hours and idle phases of
individual projects. You find most pages under **Reports** → **Projects &
customers** and **Reports** → **Resources**.

## Period, filters and export

- You choose the period with the period selector in the header. The filter
  bar only shows it as a hint; deviations are described with the respective
  report.
- Filters take effect as soon as you select them. The **Include hidden
  customers** switch only appears if customers are marked with **Hide in
  reports**; without it, their data stays out.
- Some reports have the **Area** field. It only appears for administrators,
  who use it to switch between their own data and the entire team. Everyone
  else always sees their own data there.
- **PDF** downloads a print version; **CSV** and **Excel** are available under
  **Export**. Exports keep the filters you have set. PDF and CSV exports are
  recorded in the audit log.

## Operations

**Reports** → **Projects & customers** → **Operations** opens the
**Operations report**: service orders (orders of the service order type),
tasks and tours in the period.

- Tiles: **Service orders** with the completion rate (**Completion**),
  **Service time Σ**, **Orders** with the number of overdue orders and their
  completion rate (the tile changes colour as soon as a task is overdue), and
  **Tours** with planned kilometres and planned duration.
- Charts: **Service orders: created vs. completed per week** and **Backlog per
  customer (top 15)** with the service orders still open per customer.
  Clicking a bar opens the customer's open issues; this requires the **View
  reports** permission.
- Tables: **Service orders – status**, **Service orders – priority**, **Tasks –
  status**, **Tasks – priority** and **Tours – per employee** (tours,
  **Planned km**, **Planned duration**).

Service orders are combined into four groups: **Open** (planned or accepted),
**In progress**, **Problem** (waiting for a response or for material) and
**Done** (completed, signed off or invoiced). Cancelled orders count towards
the total, but neither towards a group nor towards the completion rate. The
scheduled date of the order is decisive. Tasks count if they were created,
changed or due in the period; archived tasks are left out.

Filters: **Area**, **Customer**, **Project**, **Employee**, **Order status**
and **Include hidden customers**. Customer and project apply to service orders
and tasks, the employee to all three areas; tours know neither customer nor
project. The **Order status** only narrows down the tiles and tables of the
service orders.

Without administrator rights you see service orders assigned to you, tasks
that are assigned to you or that you created, and your own tours. Export as
PDF, CSV and Excel.

## Time allocation

**Reports** → **Projects & customers** → **Time allocation** opens the **Time
allocation by dimension** page. It shows how split time entries of the period
are distributed across **Orders**, **Assets**, **Projects**, **Cost centers**,
**Sites**, **Vehicles**, **Activities** and the free dimensions from **Time
dimensions**.

- Tiles: **Allocated time** and the number of **Dimensions**.
- One card per dimension with **Target**, **Minutes** (shown as hours and
  minutes) and **Entries** (number of time entries), sorted by time in
  descending order.
- The data basis is exclusively the allocation shares. Time that has not been
  split appears in the other time reports.

The page shows the whole organization and has no further filters. It appears
in the menu for administrators and for roles with the **View reports**
permission. Export as PDF, CSV and Excel.

## Procedure deviations

**Reports** → **Projects & customers** → **Procedure deviations** evaluates the
deviations recorded while procedures were carried out in the period. The menu
item appears with the **View procedure deviations** permission.

- Tiles: **Deviations**, **Critical**, **Share with follow-up** (share with an
  open issue or a follow-up order) and **Avg. hours to decision** (from
  creation to risk acceptance, decided deviations only).
- Charts: **Deviations by type**, the deviations over time by severity, and
  **Procedures with the most deviations (top 10)**.
- List: **Date**, **Procedure**, **Step**, **Type**, **Severity**,
  **Follow-up** (**Open issue** or **Follow-up order**), **Risk accepted on**
  and **Hrs to decision**. The icon at the end of the row opens the procedure
  run.

Filters: **Procedure**, **Type**, **Severity**, **Risk accepted** (**Accepted
only** or **Open only**) and **Follow-up action** (**With open
issue/follow-up order** or **Without follow-up**). Export as PDF, CSV and
Excel; CSV and Excel additionally contain the proposed action and the reason.

## Blocked procedure runs

**Reports** → **Projects & customers** → **Blocked procedure runs** shows runs
that are waiting for a waiting period, a second person or a risk decision.
The menu item appears with the **View procedure runs** permission.

- **Currently blocked**: **Procedure**, **Block reason**, **Blocked since** and
  **Hours**, plus a jump into the run. Block reasons are a critical deviation
  without a risk decision, a waiting period that has not yet elapsed, and a
  missing second person. When the page is opened, elapsed waiting periods are
  released.
- **Blocks ended in the period**: per block reason and procedure **Count**,
  **Avg. hours** and **Longest (h)**.

Here you set the period in the from–to field of the filter bar; without your
own entry the period selector in the header applies. Export as CSV and Excel;
it contains the ended blocks.

## Materials

**Reports** → **Resources** → **Materials** opens the **Material consumption**
page. It is based on the material lines on timesheets whose working day lies
in the period.

- Charts: **Consumption value per material (top 20)** and the material costs
  over time.
- Tiles: **Materials**, **Usages** and **Net Σ**.
- Table **Consumption per material**: **SKU**, **Material**, **Unit**,
  **Quantity**, **Usages** and **Net**, sorted by net amount in descending
  order. The same material in different units appears in separate rows; lines
  without a material master record appear under their description.

Filters: **Area**, **Customer**, **Project** and **Include hidden customers**;
the customer applies via the project of the timesheet. Without administrator
rights you only see your own timesheets. Export as PDF, CSV and Excel.

## On-call

**Reports** → **Resources** → **On-call** opens the **Emergency duty report**
with standby shifts and actual assignments per employee, as maintained in the
**Work list**. Times that extend beyond the period only count proportionally;
archived entries do not count.

- Tiles: **Employee**, **Standby** (with the number of shifts), **Active
  assignments** (assignment time with the number of assignments) and **Active
  share** (assignment time in relation to standby time).
- Charts: **On-call time per employee and week** as a heat map and the
  assignments over time.
- Table per employee: **Shifts**, **Standby**, **Assignments**, **Assignment
  time** and **Active share** with a totals row.

Filters: **Area** (**Only my standby** or **Entire team**, administrators
only), **Employee** and **Team**. Export as PDF (with the heat map), CSV and
Excel.

## Product analysis

**Reports** → **Projects & customers** → **Product analysis** shows defects,
open issues and effort per asset, product group or model. The menu item
appears for administrators and with the **View reports** permission.

- **Level**: **Per asset**, **Per product group** or **Per model**; further
  filters are **Product group**, **Manufacturer**, **Customer** and **Include
  hidden customers**.
- Columns: **Assets**, **Orders** (orders for the asset created in the period),
  **Open issues** (currently open, regardless of the period), **Escalated**
  (open issues with the status **Blocked**), **Defects** (defect reports of
  these orders in the period), **Defect rate %** (defects in relation to the
  orders) and **Last incident**.
- If there are time entries from remote support sessions for the devices in
  the period, **Maintenance sessions** and **Maintenance time** are added,
  together with a chart of the maintenance time.
- Charts: **Defects in the period (top 20)** and **Defect rate (top 15)**. The
  numbers for open issues, escalations and defects as well as the bars lead to
  the corresponding detail lists.

Export as PDF, CSV and Excel.

## Project details

**Reports** → **Projects & customers** → **Project details** shows the hours
and revenue of a single project per month. The report covers the calendar
year in which the selected period begins.

- Choose **Customer** and **Project**. Without a selection, the first project
  in the list appears. The **Employee** filter is only available with an
  organization-wide view of times.
- The project card states the annual totals **Σ Std.** and **Σ €** and lists
  **Month**, **Hours** and **Revenue**; below it follows the **Breakdown per
  employee**. Revenue is the sum of the amounts stored with the time entries.
- Charts: **Hours over the selected period**, **Actual and planned hours per
  month** (plan taken from the planned minutes of the project's orders by
  their start; without plan data a line shows the median of the actual
  months) and **Hours by order type per month**.

Administrators and roles with **See all time entries** see all projects and
hours. Everyone else only sees projects on which they have booked time
themselves, and only their own hours there. Export as PDF, CSV and Excel as
soon as a project is selected.

## Inactive projects

**Reports** → **Projects & customers** → **Inactive projects** lists all
non-archived projects of the organization on which no time was booked in the
period.

- Chart **Projects by inactivity duration**: **≤ 3 months**, **3–6 months**,
  **6–12 months**, **> 12 months** and **No bookings**, measured from the last
  time entry to the end of the period.
- Table: **Project**, **Customer**, **Status** and **Last activity** (the most
  recent time entry of all).
- Filter: **Customer**.

To tidy up, select projects and choose **Archive selected**; confirm the
prompt with **Archive**. Only projects you created yourself are archived;
administrators can archive all of them. The message states the number of
projects actually archived. Export as CSV and Excel.

## Data quality

The **Data quality: mandatory classifications** page has no menu entry of its
own; you open it via a direct link, such as a bookmark. It lists orders of the
period that lack information required by the mandatory rules from
**Classifications**. The **View reports** permission is required.

- Tiles: **Orders with gaps**, **Hard gaps** (blocking rules) and **Soft gaps**
  (hints).
- Charts: orders with classification gaps over time and **Missing
  classifications per customer (top 15)**.
- **By domain** and **By phase**: where the gaps are and from which phase on
  the information is required (on creation, before completion or before
  signature).
- **Affected orders**: **Order**, **Date** and **Missing classifications** (red
  = hard, yellow = soft). **Add retroactively** opens the order.

Filters: **Customer**, **Project**, **Order type** and **Include hidden
customers**. At most the 1,000 most recent non-archived orders in the period
are checked. The page changes nothing and offers no export.
