---
title: "Qualifications"
topic: catalog.qualifications
version: 3
keywords:
    - skills
    - competencies
    - certificates
    - certifications
    - trainings
    - expertise
    - employee skills
    - licenses
audience: []
related:
    - planning.shifts
    - catalog.activity-categories
    - glossary.core
---

Qualifications record the competencies and expertise of staff, such as
certificates, authorisations or training. Each qualification has a unique
name and an optional abbreviation and description.

Set the name and, if needed, the abbreviation; the name must be unique. The
overview shows how many people a qualification is assigned to. Column
sorting helps you organise large lists quickly.

Inactive qualifications are retained but no longer offered for selection.
Set entries that are no longer used to inactive rather than deleting them, so
the history of assignments is preserved.

## Required qualifications: hint or block

You define required qualifications on the shift type and on the order
(“Qualifications” area of dispatching). A qualification only counts if it is
valid on the day of work: if the day lies before “valid from” or after “valid
until” of the assignment, it is missing.

By default the shift plan, the order and the qualification matrix only point
out the gap. If the organization switches to **Block assignment** under
“Organization and settings”, these paths reject the assignment:

- creating a shift or assigning it to another person, shift type or day –
  even when the compliance check is overridden,
- approving a shift swap (in both directions),
- shift import; the rotation leaves the duty open and counts it as skipped,
- assigning an order to a person, adding an order to a tour and accepting a
  gap suggestion.

Staffing suggestions no longer list blocked people. Existing assignments
remain; such an assignment can only be confirmed in dispatching with a
reason, which is logged.

If you change the required qualifications of an order that is already
assigned to a person, WorkDiary checks that person right away: in blocking
mode the change is rejected while one of the qualifications is missing; in
hint mode it is saved and the gap is reported as a notice.
