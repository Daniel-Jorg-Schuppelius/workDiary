---
title: "Roles & permissions"
topic: admin.roles
version: 2
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - org.members
    - roles.admin
---

## Purpose and background

Permission management controls who may see and do what in WorkDiary.
It is split into four areas: **permissions** (read-only catalogue of
granular rights in the `resource.action` scheme, e.g.
`month.approve`), **roles** (bundles of permissions, adjustable per
organisation), **groups** (pure display grouping without functional
effect) and **members** (assignment of roles).

## Requirements

- Administration rights for the organisation.
- A test account without admin rights to really verify tailoring.
- Clarity about the job profiles in the business (field service, team
  lead, accounting …).

## Recommended workflow

1. **Create or copy a role** — an existing role as base saves failed
   attempts.
2. **Tailor permissions:** rather one additional narrow role than one
   broad catch-all right (principle of least privilege).
3. **Assign to members.**
4. **Verify with the test account** before the role goes wide.

![Role management with system roles and permission counts](media/administration/rollen.png)
*Role management: the organisation’s system roles with their permission counts.*

## Practical example

For a new office clerk the role "back office" is copied from "team
lead", approval rights are removed and the role assigned. The test
with the check account shows: monthly approvals are invisible, order
creation works — exactly as intended.

## Common mistakes

- **Assigning a global admin role:** a role without an organisation
  link acts **platform-wide** across all tenants. It belongs
  exclusively to the operator and must never be assigned via
  delegable rights or the organisation UI — escalation risk.
- **Expecting an admin bypass:** sensitive modules (data protection,
  whistleblowing) require explicit assignment — even for admins. This
  is intentional.
- **Letting catch-all roles proliferate:** broad roles are convenient
  and hard to scale back later.

## Effects and next steps

Role changes take effect immediately for all assigned members — also
for menus, help content and module access. Next: maintain assignments
under "Members" and read the security notes in the admin handbook.
