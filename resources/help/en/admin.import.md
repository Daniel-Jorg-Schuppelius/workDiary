---
title: "CSV import"
topic: admin.import
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
---

The import wizard brings master data into WorkDiary via CSV – with
analysis before writing and a complete error report.

Typical workflow:

1. **Choose an entity**: e.g. customers, users, projects, teams,
   suppliers, materials.
2. **Upload the CSV** → the **preflight analysis** checks structure
   and contents without writing anything.
3. **Review the preview**: recognized rows, warnings and errors.
4. **Confirm**: the import runs as a background job.
5. **Download the error CSV**: all rejected rows with a reason –
   correct and import again.

Good to know:

- **Nothing is written** before confirmation – preflight and preview
  are safe.
- The import history shows all runs with their status and can be
  filtered by entity and state.
- Faulty rows do not abort the run; they end up in the error report.

Tips:

- Import a small test file first, then the full set.
- Mind the order: customers/teams first, then dependent data such as
  projects.
