---
title: "Classifications & Requirement Rules"
topic: admin.classifications
version: 1
audience:
    - admin
related:
    - catalog.entry-types
    - diary-entries.create
    - admin.import
    - glossary.core
---

Classifications are organisation-wide value lists per domain, such as job
types, activities, defect types, root causes, results, priorities, goodwill
and rework reasons, product groups and equipment types. Each classification
has a code, a label and optionally a colour, icon and sort order.

Platform defaults are available to all organisations; you can override them
for your organisation, add your own values, adjust the order per domain, or
deactivate a platform default for the organisation. The CSV import lets you
create or update many values at once; the required columns are domain, code
and label.

Requirement rules link a job type to a required domain and define from which
phase the entry is mandatory – on creation, before completion or before
signature – and whether the rule is blocking or merely a hint. Minimum and
maximum count, multi-selection and a JSON condition control when and how many
values are required.
