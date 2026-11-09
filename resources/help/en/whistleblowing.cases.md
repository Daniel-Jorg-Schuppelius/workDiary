---
title: "Reporting office – case handling"
topic: whistleblowing.cases
version: 4
keywords:
    - whistleblowing
    - whistleblower
    - whistleblower protection
    - internal reporting channel
    - handle report
    - acknowledge receipt
    - compliance case
    - conflict of interest
    - emergency access
    - message reporter
    - crypto shredding
audience: []
modules:
    - module.compliance
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Here you process incoming reports from internal and external
reporters. You find the **Whistleblower reports** list in the menu
**Compliance** → **Reporting Office**. The reporting office's
permission (role **Reporting Office**) is deliberately **separate**
from administration: even administrators have no insight without their
own assignment to the case. Every single access requires the matching
permission **and** the assignment to the specific case; there is no
exception for administrators.

Access requires your own two-factor authentication; without it,
WorkDiary redirects you to set it up.

**Case list**: the overview shows only master data (**Case number**,
**Category**, **Status**, **Priority**, **Receipt by**, **Response
by**) – deliberately **no content preview**. Category and priority only
appear once you are assigned to the case (“Visible after assignment”).
The contents of each case are encrypted with a key of its own.

**Case detail**: the case file shows **Case information**, **Report
content**, **Assignee** and **Communication & notes**. Depending on your
permissions you can

- **Confirm receipt** (deadline **Receipt by**: 7 days after receipt),
- under **Change status**, choose the next permitted status and apply
  it with **Set status** – for example “Submitted” → “Acknowledged” →
  “Triage” → “Investigating” (in between “Awaiting reporter” or
  “Referred”) → “Closed – …”; closing requires a **Justification**,
  which is stored as an internal note,
- under **Assign assignee**, add a person by their **User ID** with a
  **Role** (**Assign**),
- record an **Internal note** (**Save note**; never visible to the
  reporting person),
- send a **Message to the reporting person** (**Send**); it appears in
  their protected mailbox.

Attachments uploaded by the reporting person are stored encrypted and
checked for malware before they are released. The **Attachments** card
shows the name and size of each file; you download released attachments
with the **Download** icon. Otherwise the card shows the scan state:
**Scan pending** while the result is outstanding, **Rejected** or **Scan
failed** – WorkDiary does not hand out these files. Without a configured
virus scanner, attachments stay pending.

**Confidentiality and conflicts**:

- **Declare conflict of interest** (justification optional) locks you
  out of the case: your assignment ends immediately, and you cannot lift
  the lock yourself.
- **Mark person concerned** locks a person out of the case by their
  **User ID** (**Mark**, note optional); existing assignments end
  immediately.
- **Emergency access** gives a person who is not assigned temporary
  access, four hours by default (user ID and mandatory reason,
  **Release**). Only another person may grant it.
- **Export case** creates an AES-256 encrypted package (reason and a
  password of at least twelve characters, **Export**); pass the password
  on separately from the package.
- Every step on the case is recorded tamper-proof in the case log.

**Deletion**: when a case is in status “Retention review”, the
**Controlled deletion** card appears. **Delete case** and the
confirmation **Delete permanently** destroy the case key: report
content, messages, attachments and assignments are then irretrievably
lost; only a content-free deletion record remains. This is
irreversible. If proceedings or a retention obligation stand in the
way, set the status “Legal hold” instead.
