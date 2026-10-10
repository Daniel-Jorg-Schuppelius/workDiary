---
title: "Organizations & tenants"
topic: admin.tenants
version: 4
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
    - organization list
audience:
    - admin
related:
    - admin.handbook
    - admin.license
    - admin.roles
    - admin.organization-settings
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

## Organization list and your own organization

The **Organisations** list with all tenants is available to platform operations
only: in the system menu (the **System** gear icon in the header) under
**Organization** → **Organisations**. Platform operators without an
organization of their own also find the **Employees** item in the
administration menu (the **Administration** icon in the header) under
**Personnel**; it leads to the organization list as well. When such an
administrator opens the list, WorkDiary assigns their account to the
organization created first – after that, **Employees** leads to the member
management of that organization.

Administrators of an organization edit their own organization in the system
menu under **Organization** → **Organization** (dialog **Edit organisation**;
details in the “Organization and settings” topic). **Plan** and active status
are set by platform operations only: organization admins see the plan in the
**Plan & status** section for information only (“The plan follows the license
and is maintained by the operator.”) and have no switch for the active status.
