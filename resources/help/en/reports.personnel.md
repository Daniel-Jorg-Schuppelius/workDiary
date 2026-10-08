---
title: "Personnel: vacation, sickness, qualifications, safety"
topic: reports.personnel
version: 1
keywords:
    - absence rates
    - remaining vacation
    - vacation report
    - sick days
    - continued pay
    - sick note
    - qualification matrix
    - expiring certificates
    - analyse accidents
    - near miss
    - training needs
    - early warnings
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - absences.manage
    - reports.absence-calendar
    - time-accounts.flex
    - catalog.qualifications
    - safety.overview
    - learning.overview
---

These reports summarise personal data: vacation and flexitime, sickness and
continued pay, qualifications with expiry dates, safety events as well as
recurring problems and training needs. You find them under **Reports** →
**Team** (**Vacation & flex**, **Sicknesses**, **Qualifications**,
**Occupational Safety**) and under **Reports** → **Projects & customers**
(**Problems & training**). This is particularly sensitive data: only pass
figures on to people who need them for their work. Corrections are made on the
vacation request, the sick leave, the person's qualification or the safety
event.

## Vacation & flex

**Reports** → **Team** → **Vacation & flex** shows absences and flexitime per
person for the period selected in the header. Workdays are counted: Monday to
Friday without public holidays, trimmed to the period.

Columns per person:

- **Vacation**, **Special** and **Unpaid**: workdays from approved requests of
  the respective type.
- **Sick leave**: workdays from sick leaves; cancelled sick leaves do not count.
- **Pending**: workdays from requests not yet decided, highlighted in colour.
- **Entitlement** and **Remaining** with the year: the vacation account of the
  year in which the period ends. The entitlement includes basic entitlement,
  additional leave and usable carry-over; remaining only deducts approved days
  and turns red when negative. Without a stored entitlement “–” is shown.
- **Flex Δ**: change of the flexitime balance in the months of the period
  (actual minus target according to the monthly figures of the working-time
  account).
- **Flex balance**: the last monthly balance up to the end of the period.

Tiles: **Employee**, **Vacation (workdays)** with the pending days, **Sick
leave**, **Special / unpaid** and **Flex change Σ**. The chart **Absence days
per month by type** stacks vacation, sick leave, special and unpaid; depending
on the length of the period it is per day, per week or per quarter instead.
**Remaining vacation per employee (top 15)** shows the highest remaining
balances.

Filters are only available to administrators: **Area** (**Mine only** or
**Entire team** for all people in the organization), **Employee**, **Team** and
**Status**. With **Status** only requests marked **Pending** or **Approved**
count. Export: **PDF** with chart, **CSV** and **Excel**.

## Sicknesses

**Reports** → **Team** → **Sicknesses** opens the **Sickness report** for the
period selected in the header. Cancelled sick leaves do not count.

Columns per person with sick leaves in the period:

- **Workdays** and **Cal. days**: sick days in the period, once without weekends
  and public holidays, once as calendar days.
- **Cases**: number of sick leaves; **Follow-up**: of which follow-up
  certificates.
- **With MC**: sick leaves with an uploaded certificate, relative to all cases.
- **Continued pay**: days used relative to the entitlement as a bar – green,
  orange from 75 %, red when exhausted.
- **Status**: **Exhausted** with the date on which the entitlement ends,
  otherwise the remaining free days or **OK**. Below the name, **Chain since**
  shows the start of the current sickness chain.

How continued pay is calculated: in the default setting the entitlement is six
weeks, i.e. 42 calendar days per sickness chain. A new sick leave continues the
existing chain if it is linked as a follow-up certificate or if less than six
months have passed since the end of the chain; only after at least six months
does the entitlement start again. Diagnoses are not compared. The calendar
days from the start of the chain to its end – for an ongoing sickness up to
today – count as used. The columns **Continued pay** and **Status** show
today's status, regardless of the selected period. The values are a guide, not
a legal assessment.

Tiles: **Employee**, **Workdays sick** with the calendar days, **Sickness
cases** with the follow-up certificates, **With MC** and **Entitlement
exhausted**. Charts: **Sick days per month** with a median line (per day, week
or quarter depending on the period) and the heatmap **Sick days per employee
and month**.

Filters as in **Vacation & flex**: **Area**, **Employee** and **Team**, for
administrators only. This page offers no export.

## Qualifications

**Reports** → **Team** → **Qualifications** shows the **Qualification
matrix**: one row per person with at least one qualification, one column per
qualification in the catalogue (abbreviation, full name on hover).

- Each cell shows the expiry date, or ✓ if the qualification is valid without an
  expiry date.
- Colours: green **valid**, orange **expires in 30 days**, red **expired**, grey
  **no assignment**. The legend is below the matrix.
- Tiles: **Employee**, **Qualifications**, **Assignments**, **Expiring (≤30 d)**
  and **Expired**.
- Charts: **Holders per qualification (top 15)** and **Assignments per
  qualification by status** for the twelve most common qualifications.

The reference date is always today; the header period does not change the
matrix. Filters: **Employee** and **Team**. Export: **PDF** in landscape,
**CSV** and **Excel** with one row per person and the expiry date or a validity
note per qualification. Qualifications are maintained in the catalogue and on
the person, not in the report.

## Occupational Safety

**Reports** → **Team** → **Occupational Safety** evaluates all safety events
that occurred within the header period.

- Tiles: **Total events**, **Open** (all not closed), **Closed** and
  **Critical** (severity critical).
- Charts: **Events per month** with the second series **of which closed** and
  **Events per month by status**, stacked by **Reported**, **Investigating**,
  **Measures defined** and **Closed**; per day, week or quarter depending on the
  period.
- **By kind** counts **Accident**, **Near miss**, **Hazard** and **Defect**,
  **By severity** counts **Low**, **Medium**, **High** and **Critical**.

Filters: **Employee** and **Team** – they refer to the person who reported the
event. There is no export; you handle the individual events in the safety
event register.

## Problems & training

**Reports** → **Projects & customers** → **Problems & training** opens
**Management insights**. It shows the current state without period, filters or
export.

The card **Recurring problems** collects the early warnings of the modules your
organization uses, grouped by type:

- **Rework per customer**: customers whose rework share in the last 90 days
  misses the target. Without a stored target (see “Report Targets”) no warning
  is raised.
- **Recurring defects**: objects with repeated defects in the last twelve
  months.
- **Claim patterns**: conspicuously frequent claims.
- **Recurring tickets**: customers or objects with many tickets within the time
  window; threshold and window are organization settings, by default three
  tickets in 90 days.
- **Staff shortages**: teams whose planned demand exceeds capacity in the next
  four weeks.

Each entry states the finding, a detail and a recommendation; where available,
the title leads to the affected customer, object or report. Without findings
the card shows **No notable findings.**

The card **Training needs** lists per **Competency** the **People with a gap**,
the **Avg. gap (levels)** and **Matching courses** (released courses that teach
the competency). It is based on the competency requirements per role in the
learning platform; expired certificates do not count. Without the learning
platform or without competency requirements the table stays empty.

## Who sees what

- **Vacation & flex**, **Sicknesses** and **Qualifications**: everyone only sees
  their own data. The view across all people of the organization is tied to the
  administrator role – the right **View sick leaves** alone does not unlock it
  here.
- **Occupational Safety**: menu entry and page only with the right **View
  safety event register** or **Edit / close safety events**; administrators
  always see them. In the default setup Team Lead and Management have the read
  right.
- **Problems & training**: only with the right **View reports** or as an
  administrator. In the default setup Management, Team Lead and Personnel
  Administration, among others, have it.
- The menu areas **Team** and **Projects & customers** only exist if the add-on
  module team reports is booked.
