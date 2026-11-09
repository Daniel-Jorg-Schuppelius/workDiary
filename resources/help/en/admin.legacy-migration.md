---
title: "Legacy migration"
topic: admin.legacy-migration
version: 2
keywords:
    - old system
    - previous system
    - legacy data
    - import legacy data
    - data migration
    - data takeover
    - migration
    - switchover
    - import old entries
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
- **Support portal (customers and orders)**

The import is started per area and runs the `legacy:import` command in
the background. Already imported records are linked via a legacy
identifier, so repeated runs do not create duplicates.

The **support portal** is imported into a selectable **target
organization**: customer accounts are matched to existing customers by
company name or created as customers; orders and archive become service
tickets numbered KP-… and keep appointment, technician, reply and
internal note. Passwords are not migrated; grant customer portal access
in the new system by invitation. Running it again updates tickets
already imported. Legacy times are treated as local time and converted
on import.

Note: write access depends on the configuration
(`legacy_write_enabled`). If an import fails, check the log files.
Only platform operations can open the page, because the legacy database
belongs to the whole installation.
