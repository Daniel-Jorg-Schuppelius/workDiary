---
title: "My reports"
topic: reports.my-reports
version: 1
keywords:
    - My month
    - My year
    - work balance
    - own hours
    - hours overview
    - monthly overview
    - yearly overview
    - check overtime
    - target vs actual
    - print timesheet
    - balance
audience: []
related:
    - reports.overview
    - reports.attendance
    - time-accounts.flex
    - attendance.manage
    - time-entries.edit
---

Under **Reports** → **Personal** everyone has three reports on their own time:
**My month**, **My year** and **Work balance**. They only ever show your own
entries. They are based on your time entries; the work balance additionally
uses your clock-in times and your working-time model. The reports are not a
separate data source: if a figure is wrong, correct the time entry or the
clocking – the report recalculates the next time you open it.

## Choosing the period

All three pages follow the period selected in the header. Click the calendar
icon (**Choose period**) and pick an entry under **Quick selection**, such as
**This month**, **Last month** or **This year**. The arrows **Previous period**
and **Next period** move one period back or forward; on larger screens you can
also enter your own from and to dates in the header and confirm with **Apply**.
The active period is shown as a hint in the filter bar of the page.

- **My month** always shows the calendar month in which the period starts.
- **My year** shows the calendar year in which the period starts.
- **Work balance** evaluates the period exactly from its first to its last day.

## My month

**Reports** → **Personal** → **My month** lists all your time entries of the
month day by day.

- Each day starts with a header row showing the date, the day's total duration
  and the day's total revenue; Sundays are highlighted in red.
- Below it are the entries with the columns **Time** (start and end), **Type**,
  **Customer / project**, **Activity / description** (task and description),
  **Duration** and **Revenue**. Revenue is the amount calculated for the entry;
  non-billable time shows 0 €.
- Above the table you see the monthly totals for hours and revenue, at the end
  the row **Total**.
- Two charts: **Hours per day** as a line over the month and **Hours per week by
  kind**, stacked by type for each calendar week.

Filters: **Customer**, **Project** and **Type** with **All**, **Work**,
**Journey** (marked as **Travel** in the table) and **Standby**. A selection
takes effect immediately; **Reset** clears all filters.

Export: the **PDF** button creates the daily list with totals and a chart of
hours per day. The **Export** menu offers **CSV** and **Excel** with one row per
entry: date, start, end, type, customer, project, task, description, minutes and
revenue. All exports apply the filters you have set.

## My year

**Reports** → **Personal** → **My year** shows your hours across the whole
calendar year.

- The tile **Yearly total** shows the hours of the year.
- The heatmap **Hours per day** has one row per month and one column per day
  (1 to 31). The stronger a cell's colour, the more hours; the colour scale is
  based on the highest daily value of the year. Hovering shows the date and
  hours, Sundays are marked in red.
- The bar chart **Hours per month** shows the monthly totals.
- Clicking a month name in the heatmap or a bar opens **My month** for exactly
  that month, with the same filters.

Filters: **Customer**, **Project** and **Type** as in **My month**. This page
has no export.

## Work balance

**Reports** → **Personal** → **Work balance** compares target, attendance and
recorded time for the selected period. The tiles at the top:

- **Target**: target time from your working-time model. Public holidays and days
  of approved vacation have no target.
- **Attendance**: your clock-in times minus breaks. Cancelled clockings do not
  count; a clocking that is still running is counted up to the current time.
- **Captured**: your time entries of the kinds work and travel. Standby and
  entries with the activity **Break** or **Absence** do not count.
- **Unassigned**: attendance not yet covered by time entries (attendance minus
  captured time, never negative).
- **Balance**: captured minus target – green when positive, red when negative.

Below are the charts **Actual and target hours per day** (for periods longer
than 62 days **Actual and target hours per calendar week**) and **Actual and
target hours per month**, each with a median line. The block **Distribution by
activity** lists the captured hours per activity. The table shows, per day,
**Date**, **Target**, **Attendance**, **Break**, **Captured**, **Unassigned**
and **Balance** plus the row **Total**; days without any target, attendance or
captured time are left out. Clicking a column heading sorts the table.

Export: **PDF** with key figures and the daily table.

## Who sees what

- **My month** and **My year** always show only your own entries, also for
  administrators.
- **Work balance** shows your own balance by default. Only administrators see a
  filter bar with **Employee** and **Team** and can use it to open the balance
  of another person in the same organization; **Team** only narrows the list of
  employees to choose from.
- The work balance only calculates the selected period. It does not show the
  running balance of your working-time account – see “Working-time account &
  month approval” for that.
