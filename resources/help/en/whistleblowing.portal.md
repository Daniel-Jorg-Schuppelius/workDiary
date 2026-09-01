---
title: "Configure the reporting portal"
topic: whistleblowing.portal
version: 1
audience:
    - admin
modules:
    - module.compliance
related:
    - whistleblowing.cases
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

This is where you configure your organization's public reporting
portal (`/compliance/portal`). There is exactly one portal per
organization. Managing it requires the **whistleblowing.settings.manage**
permission as well as the reporting office's two-factor
authentication.

Settings:

- **Enabled (`is_enabled`)**: makes the public portal available.
- **Allow anonymous reports**: permits reports without any identity
  details.
- **Allow confidential reports**: permits reports where the identity
  is handled confidentially.
- **Intro text** and **default language** for the reporter view.
- **Retention (months)**: deadline for the controlled deletion of
  closed cases.

**Portal link (slug)**: The public link contains a random slug (e.g.
`wb-xxxxxxxxxxxx`) and is **not** derivable from the organization
name. Use **Rotate link** to generate a new slug.

Risk: After rotating, **already distributed links become invalid
immediately**. Only use this when a link should no longer be usable,
and actively communicate the new link afterwards.
