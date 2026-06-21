---
title: "Legacy migration"
topic: admin.legacy-migration
version: 1
audience:
    - admin
related:
    - admin.import
    - admin.data-transfer
    - admin.handbook
---

Legacy migration transfers data from the legacy system into WorkDiary
and shows the migration status per data area. A configured database
connection to the legacy system is required; if it is unreachable,
the area is shown as "not configured".

The overview compares, per area, how many records exist in the legacy
system and how many have already been imported:

- **Users**
- **Diary entries**
- **On-call shifts**
- **Emergency assignments**

The import is started per area and runs the `legacy:import` command in
the background. Already imported records are linked via a legacy
identifier, so repeated runs do not create duplicates.

Note: write access depends on the configuration
(`legacy_write_enabled`). If an import fails, check the log files.
Access requires the permission to view audit logs.
