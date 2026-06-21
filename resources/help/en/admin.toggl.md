---
title: "Toggl Import"
topic: admin.toggl
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

The Toggl import brings time entries from Toggl Track into
WorkDiary. The import is **one-directional** (read only) – no times
are written back to Toggl.

Two import paths:

- **API import**: pulls time entries directly via the Toggl API. You
  store an API token and choose a date range.
- **File/export import**: uploads a Toggl detailed report (CSV) or a
  complete workspace export archive and imports from it.

Inbox (unmatched entries):

- Toggl clients/projects that could not be matched automatically to
  a WorkDiary customer/project collect here – grouped with count,
  duration and date range.
- You assign each group to an existing customer/project, create new
  ones, or dismiss entries.
- The first import is done manually; future imports match
  automatically using stored mappings.

Mappings:

- Stored links remember which Toggl client or project belongs to
  which WorkDiary customer/project.
- Mappings can be changed (remapped) or deleted (back to manual
  matching).

Risks: re-importing the same date range can create duplicates if the
source data changed – check the range and inbox before applying.
Dismissing entries is permanent.
