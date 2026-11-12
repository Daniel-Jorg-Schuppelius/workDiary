---
title: "Classifications & Requirement Rules"
topic: admin.classifications
version: 2
keywords:
    - value lists
    - categories
    - picklists
    - dropdown values
    - lookup lists
    - order types
    - fault types
    - root causes
    - mandatory fields
    - required information
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - user
    - aussendienst
    - callcenter
    - support
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

Further domains come with the industry profiles, such as service types, care
levels and communication channels (care), event types (catering) and load
carriers (freight forwarding). The **asset categories** domain extends the
general IT categories of the asset form: an organisation value with its own
code appears there in addition, one with the code of a general category
replaces its label, and a deactivated one hides it. A category used by an
asset can only be deactivated, not deleted.

A value that records still use – such as time entries, claims, articles or
assignments – cannot be deleted. The message states where and how often it is
used. Retire it instead with **Retire** in its row: it disappears from new
selections, existing records keep it.
