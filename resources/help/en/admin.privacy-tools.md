---
title: "Privacy Tools"
topic: admin.privacy-tools
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

This area bundles privacy-related tools for your organization on a
single page. It is permission-protected (privacy right) and covers
the whole organization, not just your own account.

Status overview:

- Active members, active sessions and API tokens at a glance.
- Data categories with sensitivity, retention periods and deletion
  path.

Active sessions:

- Table of the organization members' signed-in sessions with IP
  address, browser/device and last activity.
- Individual sessions can be **revoked** (forced sign-out).

API tokens:

- Overview of personal access tokens (name, user, created, last
  used, expiry).
- Tokens can be **revoked**. Revoked tokens lose validity
  immediately.

Logs:

- Recent tenant export events (who, when, format/scope).
- Recent support accesses (remote support/support) for traceability.

Data export:

- Generates a machine-readable report (JSON/CSV) with organization
  metadata, sessions, tokens and the export and support logs – as a
  basis for access and portability requests (Art. 20 GDPR) at the
  organization level.

Risks: revoking a session or token takes effect immediately and may
interrupt running integrations or logins. The export contains
personal administrative data – treat it confidentially and share it
only with authorized recipients.
