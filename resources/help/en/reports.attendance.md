---
title: "Attendance, plan/actual and coverage"
topic: reports.attendance
version: 1
keywords:
    - attendance report
    - analyse clock-in times
    - plan vs actual
    - target vs actual staffing
    - understaffing
    - shift coverage
    - minimum staffing
    - late start
    - core time
    - team monthly report
    - hours per employee
    - person-days
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - reports.my-reports
    - attendance.manage
    - planning.shifts
    - reports.utilization
    - reports.presence-emergency
---

These reports compare who was present and when with what was planned: clock-in
times against the working-time model, shifts against target staffing and
planned order time against booked time. They comprise **Attendance** and
**Plan/actual** in the menu area **Reports** → **Personal** as well as
**Coverage** and **Month per employee** under **Reports** → **Team**. All pages
only show data of the active organization. Corrections are made on the
clocking, the time entry, the working-time model or in the shift plan; the
report is not a separate data source.

## Attendance

**Reports** → **Personal** → **Attendance** opens the **Attendance report** for
the period selected in the header (calendar icon **Choose period**).

The table has one row per person and the columns:

- **Workdays** and **Target**: days and target time according to the
  working-time model per weekday. Public holidays and vacation do not reduce
  this target here – unlike in **Work balance**. Without a working-time model
  both values are 0.
- **Present**: completed clockings after deducting breaks; running and
  cancelled clockings do not count.
- **Booked**: all time entries in the period, regardless of type.
- **Balance**: present minus target, red when negative, green when positive.

The row **Total** and the tiles **Target**, **Present**, **Booked** and
**Balance** summarise all people shown. The heatmap **Attendance per employee
and weekday** shows on which weekdays someone was present and for how long;
**Attendance over time** shows the total per day, or per calendar week for
periods longer than 62 days. Clicking a column heading sorts the table.

Filters are only available to administrators: **Area** with **Mine only** or
**Entire team** (all people in the organization) as well as **Employee** and
**Team**. Everyone else only sees their own row.

Export: **PDF** with table and heatmap; in the **Export** menu **CSV** and
**Excel** with workdays, target, present, booked and balance in minutes per
person plus a total row.

## Plan/actual

**Reports** → **Personal** → **Plan/actual** compares target and actual values
in several views that you switch between using the tabs at the top of the
page: **Attendance**, **Team**, **Organization**, **Shifts**, **Projects** and
**Sites**. You only see the tabs you are authorised for.

The period is not taken from the header here: set it in the filter bar with
**From** and **To**. Without a selection the current month applies; the period
is kept when you switch tabs. These pages offer no export.

### Attendance tab

The page **Plan/actual attendance** shows your own days with the tiles
**Plan**, **Actual**, **Δ** and **Warnings** and, per day, the columns:

- **Plan**: target time according to the working-time model for the weekday;
  “—” on days without a working-time model or without a workday.
- **Actual**: the clocked attendance of the day.
- **Δ**: actual minus plan, negative values in red.
- **Start P/A**: start of core time according to the working-time model and the
  first clocking of the day, followed by the deviation in minutes.
- **Warnings**: late start, when the first clocking is more than 15 minutes
  after the start of core time, and hours deviation, when actual differs from
  plan by more than 10 %. Days without a plan get no warning.

If you open the page for another person from the **Team** or **Organization**
tab, the note **View for** with their name appears at the top.

### Team and Organization tabs

**Team** shows the members of one of your teams; if you have several teams,
choose one in the **Team** field. Administrators and people with the
organization right can choose any team that is not archived. **Organization**
shows all people in the organization under **All employees**.

Both views total **Plan (h)**, **Actual (h)**, **Difference (h)** and
**Warnings** per person using the same daily logic as the **Attendance** tab.
The magnifier **Details** opens the person's daily view for the same period.
With the team right this only works for members of your own teams.

### Shifts, Projects and Sites tabs

- **Shifts**: plan is the published and confirmed shifts of the shift plan with
  the length of their time window (night shifts across midnight included);
  actual is the overlap of the assigned person's clock-in times with that
  window. Tiles **Plan**, **Actual**, **Difference** and **Coverage** (actual
  relative to plan; highlighted below 100 %). Use **Grouping** to choose
  **Daily** or **Weekly** for the chart **Plan vs. actual per day** or **Plan
  vs. actual per week**. The table **Per shift type** lists **Shifts**,
  **Plan (h)**, **Actual (h)**, **Difference (h)** and **Coverage**. Shifts
  without a time window are marked **without time window**: they have no plan,
  and the person's daily attendance counts as actual.
- **Projects**: plan is the sum of the planned minutes of the orders whose
  period touches the selected period; actual is the booked time per project.
  Orders receive planned minutes, for example, from confirmed Calendly
  appointments. Tiles **Plan**, **Actual**, **Difference** and **Billable
  (actual)**, the chart **Top projects: plan vs. actual** (the twelve projects
  with the most actual hours) and the table **Per project** with **Project**,
  **Customer**, **Orders (planned)**, **Plan (h)**, **Actual (h)**, **Billable
  (h)** and **Difference (h)**. Projects without planned orders carry the note
  **without target data** – this is not an alarm. Time without a project is
  shown in the row **Without project**. Comparisons with time and money budgets
  are provided by **Profitability**.
- **Sites**: there is no target data for sites; the view only shows the actual
  distribution of time from location-based time tracking. Tiles **Actual**,
  **Site visits** and **People**, the chart **Actual times per site** and the
  table **Per site** with **Location**, **Customer**, **Site visits**,
  **People**, **Actual (h)** and **Share**. Visits to geofences without an
  assigned site appear under **Without site assignment** with the note
  **Geofence without site**.

Long tables in the **Projects** and **Sites** tabs are split into pages of 50
rows each.

## Coverage

**Reports** → **Team** → **Coverage** compares target and actual staffing:
do the planned shifts meet the target staffing?

- Target is the minimum value (**Min**) of the **Target staffing** you define in
  the duty plan per shift type. An entry for a specific date takes precedence
  over an entry for the weekday, which in turn takes precedence over a general
  entry. Shift types without target staffing do not appear.
- Actual is the number of scheduled shifts per shift type and day; all shifts
  that are not cancelled count, drafts included.
- Counting is in person-days: one shift of one person on one day is one
  person-day.

Tiles: **Shift types** (with the number of days evaluated), **Target
(person-days)**, **Actual (person-days)** with the difference, **Fulfilment**
(actual relative to target) and **Days with shortfall**. The heatmap
**Coverage rate per shift type and weekday** shows actual/target and the
percentage in each cell; **Missing person-days per week** shows the gaps per
calendar week. The table **Per shift type** lists **Target**, **Actual**,
**Difference**, **Fulfilment** and **Days under**; below it, **Days with
shortfall** lists each affected day with **Date**, **Shift type**, **Target**,
**Actual** and **Gap**.

The period comes from the header, at most 400 days – a longer period is cut off
after 400 days. Filter **Team**: it only counts shifts of team members; the
target stays unchanged. Export: **PDF** with heatmap and shortfall days,
**CSV** and **Excel** with the values per shift type.

## Month per employee

**Reports** → **Team** → **Month per employee** (page title **Team monthly
report**) shows the booked hours of all people across one calendar year – the
year in which the header period starts.

- The table has one row per person with time entries in the year, one column
  per month, the yearly total of hours and the total revenue in euros; the last
  row totals each month.
- Above the table are the yearly totals of hours and revenue.
- The chart **Hours per employee** shows each person's yearly total with a
  median line, the heatmap **Hours per employee and month** the distribution
  over the year.
- All time entries count, regardless of type.

Filters: **Employee** and **Team**. Export: **PDF** in landscape with heatmap,
**CSV** and **Excel**.

## Who sees what

- **Attendance**: everyone sees their own row, administrators the whole
  organization.
- **Plan/actual**: everyone sees the **Attendance** tab with their own days. The
  **Team** tab requires the right **View presence report (team)**, the tabs
  **Organization**, **Shifts**, **Projects** and **Sites** the right **View
  presence report (organization)**. Administrators see all tabs. In the default
  setup the Team Lead role has the team right, Management the organization
  right and Personnel Administration both.
- **Coverage** and **Month per employee** are only open to administrators.
  Other people see the menu entries but get a message about missing permission
  when opening them.
- The menu area **Team** only exists if the add-on module team reports is
  booked.
