---
title: "Roles & permissions"
topic: admin.roles
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
    - roles.admin
---

Access management is split into four areas:

- **Permissions** (read-only): catalog of all granular rights in the
  `resource.action` scheme (e.g. `finance.transfer.time`,
  `month.approve`).
- **Roles**: bundles of permissions, adjustable per organization.
- **Groups**: purely visual grouping of permissions for the
  overview – no functional effect of their own.
- **Members**: assignment of roles to organization members.

Typical workflow: create or copy a role → tailor the permissions →
assign it to members → verify with a test account.

Security principles:

- **Global admin role**: a role without an organization reference
  acts **platform-wide** across all tenants. It is reserved
  exclusively for the operator and must **never** be granted via
  delegable permissions or the organization UI – escalation risk!
- Principle of least privilege: prefer an additional narrow role over
  one broad catch-all right.
- Deliberately no admin bypass in sensitive modules (e.g. data
  protection, whistleblowing): these rights must be granted
  explicitly – even to admins.
