---
title: "OpenProject Integration"
topic: admin.openproject
version: 3
keywords:
    - project management
    - work packages
    - time entries
    - import time
    - push time entries
    - time sync
    - project sync
    - project mapping
    - task import
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
    - admin.integration-inbox
---

The OpenProject integration couples WorkDiary **bidirectionally** with
OpenProject: times are imported **and** recorded times can be posted
back to OpenProject. You store credentials and options in the plugin
settings (including **Instance URL**, **API token** and **Sync window
(days)**).

Synchronizing (**Sync OpenProject** page):

- **Sync structure + times** with **Synchronize now**: reconciles
  projects and work packages and then imports the time entries within
  the configured window.
- **Sync structure only** with **Reconcile structure**: maps projects,
  work packages and users from OpenProject to WorkDiary projects, tasks
  and users. If **Create missing projects/tasks** is switched on, the
  reconciliation creates missing entries automatically.

Unassigned time entries:

- Anything that cannot be assigned automatically ends up in the central
  **Mapping Inbox**; **To mapping inbox** takes you there and shows the
  number of open entries.
- There you assign a group to a customer and project (or name a new
  one) and book it, or you dismiss it. Future imports map
  automatically based on the stored mappings.

Posting back (**Post times back**):

- Writes non-exported times of projects mapped to an OpenProject
  project back to OpenProject; tasks are booked as work packages if
  they are mapped. **Period (optional)** limits the run (empty = all
  open entries), **Post back now** starts it and **Last post-back** shows
  the result. Entries already posted are skipped.
- Prerequisite: the **OpenProject activity ID (post-back)** must be set
  in the plugin settings – otherwise no posting back is possible.

Mappings (**Manage mappings**):

- The **OpenProject – mappings** page lists the stored links for
  projects, work packages and users. **Reallocate** changes the target,
  **Remove** deletes a mapping.

Risks: posting back changes data in the connected OpenProject system.
Before the first run, check the mappings and the activity ID to avoid
misbookings.
