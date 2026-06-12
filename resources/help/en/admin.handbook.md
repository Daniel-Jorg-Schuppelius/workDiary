---
title: "Admin handbook: overview"
topic: admin.handbook
version: 1
audience:
    - admin
related:
    - admin.tenants
    - admin.roles
    - admin.backups
    - admin.license
    - admin.import
    - admin.security
    - roles.admin
---

The admin handbook bundles all administrative topics in WorkDiary.
The chapters (see related topics below):

- **Organizations/tenants**: creating, deactivating, GDPR export and
  deletion, switching organizations.
- **Roles & permissions**: granular permission model, roles, groups,
  member assignment – and why the global admin role is off-limits.
- **Backups & operations**: backup heartbeat, checking system health.
- **License**: plan, modules, limits, organization-bound licenses.
- **Import**: CSV wizard with preflight analysis and error report.
- **Security**: 2FA methods, encrypting existing data, audit chain,
  SBOM/components.

Recommended order for initial setup:

1. check organization and license,
2. set up roles and members,
3. import master data,
4. configure rules (notifications, surcharges),
5. arm security and backup monitoring.

Principle: for corrections always use the functional path (correction
request, cancellation, new version) instead of admin overrides – this
keeps the audit trail and traceability intact.
