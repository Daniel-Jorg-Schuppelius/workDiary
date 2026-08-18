---
title: "Toggl Import"
topic: admin.toggl
version: 2
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

The Toggl import brings time entries from Toggl Track into
WorkDiary. By default the import only reads; optionally corrections
can be written back (“Write back corrections”) and locally recorded
times can be transferred (“Enable time transfer”).

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

User assignment (MVP-509):

- Every Toggl entry is assigned to the matching WorkDiary user via the
  workspace user email: stored mappings ("Manage mappings") first, then
  email equality.
- Unknown or unreachable Toggl users are never booked silently to the
  main user — they land as an open case in the assignment inbox. There
  you pick the user; the choice is remembered and future imports book
  correctly on their own.
- Only in the explicitly enabled single-user mode (plugin setting) does
  the import book entries without a user signal to the default user.
- Previously misassigned imports are repaired by
  `toggl:repair-entry-users` (dry run first, write with `--apply`);
  billed or signed times are never changed automatically.
- For the one-time **workspace import** (folder/ZIP or API) you choose
  the user assignment explicitly: assign to existing users only
  (unknown entries stay visibly unbooked and are listed per email),
  create missing users per email, or single-user mode (everything onto
  the configured default user — clearly named in preview and result).
  Individual Toggl addresses can additionally be mapped explicitly; the
  import is idempotent and can simply be re-run once mappings are
  maintained.
