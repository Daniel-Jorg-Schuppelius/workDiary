---
title: "License management"
topic: admin.license
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
---

The license page shows what your installation may do: **plan**
(free/pro/enterprise), **user/organization limits**, enabled
**modules** and the **expiry date**.

How it all fits together:

- The **license is the source** for plan and add-on modules; the
  plan → modules mapping lives in the configuration. New modules of a
  plan therefore become available without re-issuing the license.
- **Organization-bound licenses** can be installed and removed per
  organization; if an org license is missing, the global license
  applies as a fallback.
- **Without a valid license** the installation runs hard on the free
  plan.

Typical actions:

1. Check license status and modules.
2. Override **feature flags** selectively (override toggle).
3. **Install/remove** an org license or – if your installation is
   entitled to – **issue** new licenses (licensee, e-mail, plan,
   add-ons, expiry, limits, organization, domain).

Tenant status (SaaS):

- The **tenant status** shows whether the organization is in **trial**,
  **active** or **suspended**. If no status is set explicitly, it is
  derived from the trial period and the license expiry (valid / in
  grace / expired).
- A platform admin can set the status manually to **active**, **trial**
  or **suspended**, or release it again via *Automatic (derive)*.
- When **suspended** (or the license has finally expired), **write
  actions are disabled**; reading stays possible. The license and
  logout pages remain reachable so the lock can be lifted.
- The license **user limit** is enforced when creating new members: if
  the limit is reached, creation is blocked with a notice.

Good to know:

- Plan downgrades lock modules via plan gating; contents of modules
  with retention obligations are preserved.
- No files are uploaded – licenses are entered as signed keys.
