---
title: "CSV import"
topic: admin.import
version: 3
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.tenants
    - contacts.manage
---

## Purpose and background

The import wizard brings master data into WorkDiary via CSV — with
analysis **before** writing and a complete error report. It is the
fastest way to take over an existing base (customers, users,
projects, teams, suppliers, materials) in a structured way without
leaving data quality to chance.

## Requirements

- Administration rights.
- One CSV file per entity; column mapping happens in the wizard.
- For dependent data: the right **order** (customers/teams first,
  then projects etc.).

## Recommended workflow

1. **Choose the entity** (e.g. customers, users, projects, teams,
   suppliers, materials).
2. **Upload the CSV** — the **preflight analysis** checks structure
   and content without writing anything.
3. **Check the preview:** recognised rows, warnings, errors.
4. **Confirm** — the import runs as a background job.
5. **Download the error CSV:** all rejected rows with reasons;
   correct and import again.

![Import wizard with entity choice, sample template and preflight](media/administration/import-assistent.png)
*The import wizard: choose the entity, download the template, upload the file — the preflight writes nothing.*

## Practical example

During migration a business first imports a test file with ten
customers, checks preview and field mapping, then loads the full set
of 800 rows. Twelve rows land in the error report with reasons, are
corrected and taken over in the second run.

## Common mistakes

- **Loading the full set without a test file** — mapping errors
  multiply needlessly.
- **Ignoring the order:** projects before their customers fail on
  missing references.
- **Ignoring the error report:** faulty rows do not abort the run —
  but they are missing from the base until re-imported.

## Effects and next steps

Nothing is written before confirmation — preflight and preview are
safe. The import history shows all runs with status and can be
filtered by entity and state. Next: spot-check imported master data
and clean duplicates via merging.

## Transaction and measurement data

Besides master data, the import also takes ongoing data: meter readings,
ESG consumption (activity, quantity, period and data quality), inspection
measurements for existing inspections and equipment positions from
telematics exports. For positions, WorkDiary reports when rented equipment
leaves its site.

## Attendances and project times from the calendar

For attendances and project times you can choose a connected calendar
source instead of a file (CalDAV, Google Calendar, Microsoft 365). The
events of the chosen period are fetched and checked like an iCal file —
with preview, category filter and resolution of series.
