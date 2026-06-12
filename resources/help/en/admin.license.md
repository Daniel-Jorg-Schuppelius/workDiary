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

Good to know:

- Plan downgrades lock modules via plan gating; contents of modules
  with retention obligations are preserved.
- No files are uploaded – licenses are entered as signed keys.
