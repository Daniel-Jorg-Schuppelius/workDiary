---
title: "Toggl Import"
topic: admin.toggl
version: 4
keywords:
    - Toggl Track
    - import time entries
    - Toggl CSV
    - Toggl API
    - workspace export
    - detailed report upload
    - mapping
    - migrate from Toggl
    - user mapping
    - write back corrections
    - new Toggl
    - Toggl 2.0
    - Toggl Focus
    - live sync
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

The Toggl import brings time entries from Toggl Track or the
new Toggl into WorkDiary. By default the import only reads; optionally corrections
can be written back (“Write back corrections”) and locally recorded
times can be transferred (“Enable time transfer”).

Toggl Track or the new Toggl:

- Under **Toggl product** in the plugin settings you choose where times
  come from: **Toggl Track** or the **new Toggl** (formerly Toggl 2.0 or
  Toggl Focus). Reading, writing back and transferring happen only in the
  selected product.
- For the new Toggl you store its API key (starts with `toggl_sk_`). The
  organization and workspace ID are optional; without them the plugin
  finds the workspaces through your Toggl account. Toggl allows only one
  active key per user – creating a new one invalidates the previous key.
- If you use live sync between Track and the new Toggl, still choose only
  one source: Toggl itself keeps the two products in sync.
- After a switch, the next import assigns entries already imported to the
  new source (same user, start and end) instead of creating them again;
  open cases in the assignment inbox move along. This applies to the
  fetch period. Older entries keep their previous link, and corrections to
  them are no longer sent to Toggl. If needed, import once with a longer
  period (`toggl:import --days=365`).
- Deletion detection only considers entries of the selected source.

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

Webhook (optional):

- To receive new entries faster, the import page shows the address for a
  Toggl webhook subscription in the **Webhook (optional)** section. Create
  the subscription for the workspace in Toggl and enter the secret assigned
  there in the plugin settings under **Webhook secret**; the **Workspace ID**
  must be set there as well.
- The webhook only triggers the same import that the hourly fetch runs. If
  it fails, the fetch catches up.
- The new Toggl offers no webhooks. With live sync active, a subscription
  in Toggl Track can still trigger the fetch earlier; data is still read
  from the new Toggl.
- You choose the default user of single-user mode in the plugin settings
  under **Book times for user** from the list of users.
