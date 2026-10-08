---
title: "Using reports"
topic: reports.overview
version: 3
keywords:
    - statistics
    - KPIs
    - analytics
    - drilldown
    - export report
    - revenue by product
    - minimum wage check
    - driving and rest times
    - liquidity forecast
    - cash flow forecast
    - reporting
    - find a report
audience: []
related:
    - reports.my-reports
    - reports.attendance
    - reports.personnel
    - reports.operations
    - reports.fleet
    - reports.billing
    - reports.compliance
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.drilldown
    - reports.saved-views
    - exports.payroll
---

The menu area **Reports** in the sidebar brings all reports together – from
your own monthly overview to finance and audit reports. Reports condense
existing data such as time entries, clockings, absences, orders and invoices by
period, person, team, project or customer. They are not a separate data source:
corrections belong on the original order, time, absence or master-data record.
Apply need-to-know access to personal and financial reports. This topic
explains how the reports are structured and leads you to the topics for the
individual reports.

## The Overview page

**Reports** → **Overview** shows your own key figures for the header period at
the top: **My hours**, **Days recorded**, **Ø per day** (based on the days
recorded) and **Active projects** (projects you booked time to). The first chart
shows your hours – up to 31 days as **Hours per day**, up to about half a year
as **Hours per week**, beyond that as **Hours per month**. **Top projects by
hours** lists your ten projects with the most hours.

Below, there is one card per menu group with all reports you may open. The
selection exactly matches the sidebar.

## Menu and visibility

The reports are arranged in groups: **Overview**, **Personal**, **Team**,
**Projects & customers**, **Resources** and **Finance & audit**. An entry only
appears if your organization uses the corresponding module and you have the
required right. The groups **Team**, **Projects & customers** and **Resources**
require the add-on module team reports. Entries you have hidden via “Customize
menu & All functions” are also missing from the overview page.

## Period

Most reports follow the period selected in the header. Click the calendar icon
(**Choose period**) and pick an entry under **Quick selection**, for example
**Today**, **This week**, **Last month**, **This quarter** or **Last 90 days**.
The arrows **Previous period** and **Next period** move on; on larger screens you
can also enter your own from and to dates in the header and confirm with
**Apply**. The period applies to all pages until you change it or log out;
without a selection **This month** applies. The filter bar shows it as a hint.

The topic of each report names any exceptions, for example:

- **My month**, **My year** and **Month per employee** show the month or year in
  which the period starts.
- **Plan/actual** has its own fields **From** and **To**; without a selection
  the current month applies.
- **Qualifications** and **Problems & training** show today's status.

A link containing dates – for example from a saved report – opens the report
with exactly that period.

## Filters

- Depending on the report, the filter bar offers fields such as **Customer**,
  **Project**, **Employee**, **Team** or **Status**. A selection usually takes
  effect immediately; **Reset** clears all filters.
- Some fields are only shown to administrators, such as **Area** with **Mine
  only** or **Entire team**. Everyone else only sees their own data there.
- Customers marked **Hide in reports** in their master data are left out of the
  customer and project reports. The switch **Include hidden customers** – it
  only appears if such customers exist – brings them back; if you select such a
  customer directly in the filter, it is shown as well.
- You can save a configured report as a named view, see “Saved reports”.

## Export

- **PDF** downloads a print version in your organization's document design;
  the **Export** menu offers **CSV** and **Excel**. Not every report has all
  formats, and some have no export.
- Exports apply the period and filters of the page.
- In the default setting, CSV files are separated by semicolons and saved in
  UTF-8. The first lines start with # and state the report, the creation time
  and a fingerprint of the filters – so a file can later be matched to its
  state.
- Exports are recorded in the audit log with report, format and filters.

## Drilldown

Many key figures, chart points and table rows are clickable and lead to the
records behind them or to a more detailed view – for example from **My year**
to **My month** or from the **Team** tab in **Plan/actual** to the days of one
person. More on this under “Drilldown from KPI to work order”.

## Rights

- The personal reports are open to everyone and only show your own data.
- Organization-wide analyses of customers, revenue and suppliers require the
  right **View reports** or the administrator role.
- Some reports have their own right, for example **View presence report (team)**
  for plan/actual or **View safety event register** for occupational safety.
- Some team views – for example **Coverage** or the team view of **Vacation &
  flex** – remain reserved for administrators.
- Every report only shows data of the active organization.

## Which report for what

The following overview follows the menu groups and names, for each report, the
topic with all the details.

### Personal

**My month**, **My year** and **Work balance** show your own time day by day,
across the year and compared with the target – see “My reports”. **Attendance**
and **Plan/actual** are also in this group; they belong to the next section.

### Attendance, planning and time accounts

- **Attendance**, **Plan/actual**, **Coverage** and **Month per employee**
  compare clock-in times, shifts and booked hours with target and planning –
  see “Attendance, plan/actual and coverage”.
- **Week per employee** shows the hours of each weekday per person with the
  weekly total, at most twelve weeks at a time. The view of all people is
  available to administrators and people with the right **See all time
  entries**.
- **Utilization** relates recorded, billable and invoiced time – see
  “Utilization & realization”.
- **Emergency roster** shows who is in the building, out of the office or
  absent – see “Emergency attendance list”.
- **Surcharge forecast** estimates the expected surcharge minutes per month and
  wage type from the planned shifts; it requires the right **View reports** –
  see “Billing, expenses, payouts and revenue”.
- **Time accounts** and **Period comparison** are explained in “Time accounts”,
  the **Absence calendar** in “Absence calendar (year overview)”.

### Personnel

**Vacation & flex**, **Sicknesses**, **Qualifications**, **Occupational
Safety** and **Problems & training** are described in “Personnel: vacation,
sickness, qualifications, safety”. **Cohort comparison** compares key figures
before and after a training course – see “Cohort comparison (before/after
training)”. **Training** belongs to “Training management”, the course
evaluation **Analysis** to “Learning platform”.

### Customers and projects

- **Customer analysis** and **Customers & projects** are explained in
  “Customer analysis”, **Customer value** and **Customer retention** have their
  own topics of the same name, **Order-type analysis** is covered in “Entry type
  analysis”.
- **Operations**, **Time allocation**, **Procedure deviations**, **Blocked
  procedure runs**, **Product analysis**, **Project details** and **Inactive
  projects** are operational reports – see “Operations: time allocation, procedures, material, on-call”. **Data quality**
  is described there as well.
- **SLA** and **SLA contracts** are described in “SLA, contracts & service
  levels”.

### Resources

- **Fleet** shows mileage, consumption, fuel and charging costs and the cost per
  kilometre for each vehicle. The **Logbook report** provides the tax logbook
  per vehicle and period with trip types and private share, plus the **1%
  comparison**. The **Driving time evidence** documents driving and rest times
  per driver. All three are explained in “Fleet, logbook and driving times”.
- **Materials** and **On-call** belong to “Operations: time allocation, procedures, material, on-call”.
- **Cloud document intake** is explained in the topic of the same name.

### Finance and audit

- **Profitability**, **Payment behavior**, **Supplier analysis** and
  **Supplier value** have their own topics of the same name.
- **Billing** and **Revenue per product** – see “Billing, expenses, payouts and revenue”. **Revenue per
  product** is calculated from local invoices and from the invoices of connected
  systems such as Lexoffice, also per article category; credit notes and
  cancellation documents reduce the revenue.
- **Expenses** summarises expenses per person, category and month; only
  administrators have the view of all people. **External payouts** calculates
  the pay of external staff without payroll and is only visible with the right
  **Manage personnel & payroll data**. Both are described in “Billing, expenses, payouts and revenue”.
- **Financial reports** and **BWA & budget** only exist with locally kept
  accounting. They read posted entries only; this includes the **Liquidity
  forecast**, thirteen weeks by default – see “Closing and reports”.
- **Working-time compliance** checks actual working times against the German
  Working Hours Act – see “Working-time compliance”. The tabs **Dashboard** and
  **Violation history** of this page are described in “Evidence: audit activity, compliance and minimum wage”. From
  there you also create the evidence for supervisory authorities: the **MiLoG
  evidence (customs)** for the minimum wage check with start, end and duration
  per working day – also in “Evidence: audit activity, compliance and minimum wage” – and, if your organization
  records driving times, the **Driving time evidence** on driving and rest
  times per driver – see “Fleet, logbook and driving times”.
- **Audit activity** summarises the audit log by event, person and object type
  and is only open to administrators – see “Evidence: audit activity, compliance and minimum wage”.
