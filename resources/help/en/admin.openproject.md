---
title: "OpenProject Integration"
topic: admin.openproject
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
---

The OpenProject integration couples WorkDiary with OpenProject
**bidirectionally**: times are imported **and** recorded times can
be pushed back to OpenProject.

Structure sync:

- Imports projects, work packages and users from OpenProject and
  links them to WorkDiary projects and tasks.
- Prerequisite for the time import.

Time import (sync):

- Brings time entries in from OpenProject.

Inbox (unmatched entries):

- OpenProject projects without an automatic match collect here (with
  count, duration and date range).
- You assign them to an existing project, create a new one, or
  dismiss them. Future imports match automatically using the stored
  mappings.

Push back:

- Writes times recorded in WorkDiary back to OpenProject. Already
  exported entries are skipped and newly exported ones are marked as
  exported.
- Prerequisite: a **default activity** (default_activity_id) must be
  configured in the plugin – otherwise the push fails.

Mappings:

- Links for projects, work packages and users. They can be changed
  or deleted.

Risks: the push back changes data in the connected OpenProject
system. Before the first push, check the mappings and the default
activity to avoid mis-postings.
