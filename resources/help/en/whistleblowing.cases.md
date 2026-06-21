---
title: "Reporting office – case handling"
topic: whistleblowing.cases
version: 1
audience: []
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

This is where you handle incoming reports from internal and external
reporters (`/compliance/meldungen`). The reporting office permissions
are deliberately **separated** from administration: a global admin
without their own case assignment has no access. Every single access
is checked by the case policy (permission **and** a concrete
assignment to the case); there is no admin bypass.

A separate two-factor authentication for the reporting office is
required before access.

**Case list**: The overview shows only master data (case number,
category, status, priority, deadlines) – deliberately **no content
preview**. Contents are encrypted per case with their own key (DEK).

**Case detail**: Depending on your permissions you can

- **acknowledge receipt** (7-day deadline),
- change the **status** along the lifecycle (Received → Acknowledged
  → Triage → Investigating → … → Closed); closing requires a reason,
- **assign handlers** (with a role),
- record **internal notes** (never visible to the reporter),
- send **messages to the reporting person** (via the anonymous
  mailbox),
- download encrypted **attachments**.

**Confidentiality and conflicts**:

- **Declaring a conflict of interest** locks you out of the case.
- **Marking an affected person** permanently locks that person out of
  the case.
- An **emergency grant** (with a mandatory reason) gives another
  person access – each of these steps is recorded in the dedicated
  event hash chain.

**Deletion**: Controlled deletion at the end of the retention period
uses **crypto-shredding** (the case key is destroyed, rendering the
contents unreadable). This is irreversible.
