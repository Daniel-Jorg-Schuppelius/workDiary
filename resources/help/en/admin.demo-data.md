---
title: "Demo data"
topic: admin.demo-data
version: 2
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Demo data populates an organization with sample data for testing,
training and presentations. The content depends on a selectable
**sample industry**: every branch profile has exactly one, with its own
customers, projects, a complete main job, material, asset, signed
protocol and procedure run.

Actions:

- **Create demo organization** (platform admin, tenant management):
  creates a new, isolated organization suffixed "(Demo)"; a platform
  admin can be assigned as a member right away.
- **Seed**: fills the current, still empty organization with sample data
  for the chosen industry. The overview indicates whether the
  organization is empty.
- **Reset**: deletes the demo data of a demo tenant and creates it again;
  industry and scope are kept.

Scope:

- Unchecked, the demo follows the **module recommendation of the branch
  profile**: the scope is set accordingly and sample data is created only
  for active modules.
- **Show the full scope** creates sample data for every module (helpdesk,
  agile, applications, investments, crisis exercise, sustainability,
  claims, rental, leasing, test equipment, accounting, learning platform
  and more).

License:

- The dialog shows in advance which license the demo will run under. If
  this instance can issue licenses, the demo organization automatically
  receives a time-limited license; otherwise the installation license
  applies. Without either, the demo runs on the Free tier and most
  modules stay locked.

Risks and limitations:

- **Reset is only allowed for designated demo tenants** (`is_demo`).
  For regular organizations it is rejected to protect real data. On a
  demo tenant, however, the reset overwrites or removes the existing
  demo data.
- Seeding adds additional records; check beforehand whether the
  organization should really be empty.
- If a retention period is configured, the scheduler permanently deletes
  demo organizations once it has elapsed.

All actions require their own permissions (seed, platform-wide reset and
creation) and are recorded in the audit log.
