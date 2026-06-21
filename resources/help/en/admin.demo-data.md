---
title: "Demo data"
topic: admin.demo-data
version: 1
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Demo data is used to populate an organization with sample data for
testing and training. The content depends on a selectable industry.

Actions:

- **Seed**: creates sample data (e.g. customers, diary entries) for
  the chosen industry. The overview indicates whether the
  organization is currently empty.
- **Reset**: resets a demo tenant.

Risks and limitations:

- **Reset is only allowed for designated demo tenants** (`is_demo`).
  For regular organizations it is rejected to protect real data. On a
  demo tenant, however, the reset overwrites or removes the existing
  demo data.
- Seeding adds additional records; check beforehand whether the
  organization should really be empty.

Both actions require their own permissions (seed, and platform-wide
reset) and are recorded in the audit log.
