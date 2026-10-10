---
title: "Evidence: audit activity, compliance and minimum wage"
topic: reports.compliance
version: 3
keywords:
    - audit report
    - who changed what
    - trace exports
    - compliance overview
    - working time violations
    - acknowledge violations
    - accept violation
    - unresolved cases
    - minimum wage evidence
    - customs inspection
    - working time record
    - recording obligation
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
related:
    - reports.arbzg-compliance
    - audit.log
    - corrections.requests
    - attendance.manage
    - reports.fleet
    - admin.organization-settings
---

These pages serve as evidence for auditors and authorities: who did what in
the system, where things stand with violations of the German Working Hours
Act (ArbZG) and how they were handled, and the working time record under the
Minimum Wage Act for customs. Which rules the working-time compliance checks
and what the detailed list looks like is described in the topic on
working-time compliance.

## Period and export

- You choose the period with the period selector in the header. The
  **Violation history**, by contrast, shows all stored violations.
- Where an export exists, it is mentioned in the respective section. Every
  export is recorded in the audit log.

## Audit activity

**Reports** → **Finance & audit** → **Audit activity** summarizes the entries
of the audit log in the period. The page only opens for administrators;
everyone else is denied access.

- Tiles: **Events Σ** (all entries in the period), **Active users** (people
  with at least one entry) and **Entity types** (distinct object types). These
  two also count across all entries in the period, not just the top 20
  lists.
- Charts: **Events over time**, **Top actors (top 15)** and the events over
  time by event type.
- Tables: **By event**, **By entity type (top 20)**, **By user (top 20)** and
  **Last 100 events** with **Time**, **User**, **Event**, **Type**, **ID** and
  **IP**. Events and types appear with their readable name where one is
  available.
- Filter: **Employee**.

Exporting reports also creates an entry with the report, format and filters;
this lets you trace who downloaded which report. Individual entries with all
details are shown in the **Audit log**. Export as PDF, CSV and Excel.

## Compliance dashboard

You open the **Compliance dashboard** via the **Dashboard** tab on the
**Working-time compliance** page (**Reports** → **Finance & audit** →
**Working-time compliance**). The tabs **Dashboard**, **Detail report** and
**Violation history** connect the three views. The **View working-time
compliance** permission is required.

The dashboard determines the findings of the period from the recorded working
times in the same way as the detail report; if the driving time rules are
switched on, the driving and rest time findings are added.

- Tiles: **Total findings** (a click opens the detail report), **Affected
  employees**, **Open (without correction)** and **With approved correction**
  (findings on days with an approved time correction).
- One tile per violation type with the count; a click opens the detail
  report filtered to this type.
- Charts: open findings over time and findings over time by violation type.
- **Violations by rule and month**: per month the findings of each violation
  type with **Total**.
- **Findings per team**: deliberately by team rather than by person. Anyone
  who belongs to several teams counts in each; people without a team appear
  under **Without team**.

Filter: **Team**. The dashboard offers no export.

## Violation history

The **Violation history** tab opens the **Compliance violations** page with
the stored violations and their processing status. A regular check, by
default once a day at night, stores new findings. If a finding is no longer
detected during this check, it is set to **Resolved**; if it occurs again, it
returns to **Open**.

- Tiles per status: **Open**, **Acknowledged**, **Resolved** and **Accepted**,
  counted across the whole organization. A click filters the list.
- Chart **New vs. acknowledged findings per month** for the last 24 months
  with data.
- List: **Employee**, **Date**, **Type**, **Value**, **Threshold**,
  **Severity** and **Status**; for processed violations, the name, date and
  reason appear below.
- Filters: **Employee**, **Team**, **Status** and **Category** (**ArbZG**,
  **Unresolved cases**, **Driving times**).

This is how you process a violation with the status **Open** or
**Acknowledged**:

1. If needed, enter a reason in the **Reason (required for “accepted”)**
   field.
2. Choose **Acknowledge** to take note of the violation, or **Accept** to
   consciously tolerate it. A reason is mandatory for **Accept**.

Every status change is recorded in the audit log. For **Unresolved cases**,
an additional icon opens a **Correction request** with the day of the
finding. The list is not limited to the period and shows 50 entries per
page. The page offers no export.

## MiLoG evidence (customs)

You download the **MiLoG evidence (customs)** on the **Working-time
compliance** page from the **Export** menu. It serves as the record under
Section 17 (1) of the Minimum Wage Act (MiLoG) and requires the **View
working-time compliance** permission.

- The CSV file contains, per employee and calendar day, **Employee**,
  **Personnel number**, **Date**, **Start**, **End**, **Breaks (min)** and
  **Duration**.
- It is based on the completed clock times; cancelled and still open clock
  entries do not count. **Start** is the first start and **End** the last end
  of the day, breaks are added up, and **Duration** is the working time after
  deducting breaks.
- It is sorted by name and date. The download takes over the period and the
  employee and team filters of the page and is recorded in the audit log.

The **Driving time evidence** in the same menu is described in the topic on
fleet, logbook and driving times.
