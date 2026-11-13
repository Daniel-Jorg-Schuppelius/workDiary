---
title: "Import lists and compare customer list"
topic: service-plan.import
version: 1
keywords:
    - import list
    - Excel
    - CSV
    - annual list
    - period list
    - customer comparison
    - DATEV
    - client list
audience: []
related:
    - service-plan.overview
    - customers.deadlines
---

## Import list

Import existing Excel or CSV lists via **Import list** in the service plan. A
**preview** first checks every row, then you import.

- **Annual list:** customer number, service, status, remark; optionally
  documents received, mandatory and responsible.
- **Period list:** customer number, service and one column per period (Jan …
  Dec, Q1 … Q4 or H1, H2) with the status.
- If there is no “service” column, choose the service for the whole list.
- Columns named like a trait of the customer deadlines (e.g. VAT interval)
  take over the traits as a new version.

Matching **never guesses**: customers only by the exact customer number,
services only if exactly one template or entry type fits, responsible people
only by the name or e-mail of a member. A row with an unknown customer number
creates nothing and is listed in the preview.

The import takes over missing scope of services (existing scope stays),
traits, remark and “documents received” of the current period (only where
nothing is recorded yet) and “not required”. On request the status of the
current period becomes an order where there is none yet.

## Compare customer list

**Compare customer list** compares your customer base with a list from
another program, for example the DATEV client export (client number, name).
The preview shows proposals:

- **New:** number unknown — create customer.
- **Renamed:** number known, name different — rename customer.
- **Missing from list:** for information only.

Nothing is overwritten or created before you tick a proposal.
