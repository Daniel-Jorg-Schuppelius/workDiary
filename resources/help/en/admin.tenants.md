---
title: "Organizations & tenants"
topic: admin.tenants
version: 2
keywords:
    - tenant management
    - create organization
    - add company
    - delete organization
    - suspend organization
    - switch organization
    - org switcher
    - data export
    - purge
    - change plan
    - multi-tenant
    - approval levels
audience:
    - admin
related:
    - admin.handbook
    - admin.license
    - admin.roles
---

This is where you manage organizations (tenants). Every organization
is an isolated unit – all data belongs to exactly one tenant.

Typical actions:

- **Create/edit**: master data and plan of the organization.
- **Deactivate/reactivate**: reversible – the organization is locked,
  data is preserved.
- **Export**: data export in the sense of data portability
  (Art. 20 GDPR).
- **Delete permanently (purge)**: erasure under Art. 17 GDPR.
- **Switch**: global admins can switch into the context of another
  organization (org switcher).

Plan and modules: every organization has a plan (free/pro/enterprise)
or an organization-bound license; this determines the enabled modules
(e.g. Finance, ISMS, data protection) – details in the **License**
chapter.

Risks and irreversible actions:

- **Purge is irreversible** – all data of the organization is deleted
  permanently (audit-logged). Always offer an export first and check
  retention obligations.
- Deactivating is the safe alternative when only access should end.

Approvals: in the section of the same name, you define which role sees
the approval steps of a contract negotiation under “Approvals”, per step
kind (commercial, technical, HR). Left empty, the default applies:
Accounting, Team Lead, Personnel Administration. Approval on the record is
not affected.
