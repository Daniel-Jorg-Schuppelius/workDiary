---
title: "Configure the reporting portal"
topic: whistleblowing.portal
version: 2
keywords:
    - whistleblower portal
    - enable portal
    - allow anonymous reports
    - confidential reports
    - portal link
    - regenerate link
    - retention period
    - intro text
    - set up reporting channel
    - default language
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

**Languages**: The **default language** determines the language in which the portal appears. Under **Additional languages** you choose the languages the reporting person can switch to; without a selection there is no language switch.

**External reporting channels**: The portal always points out that external reporting offices of the authorities are available alongside the internal office. Enter the offices responsible for your organisation with name and web address; they appear as a list in the portal.

**Version of the privacy notice**: The value appears below the introduction so that it is clear which version applies.

**Retention**: The months set here apply to every case closed from now on. Cases already closed keep the period calculated when they were closed.
