---
title: "Data protection management at a glance"
topic: privacy.overview
version: 2
keywords:
    - GDPR
    - ROPA
    - record of processing activities
    - DPA
    - data processing agreement
    - TOMs
    - data subject requests
    - DSAR
    - data breach
    - 72-hour notification
    - deletion concept
    - retention policy
    - legal hold
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

The data protection module supports your organization's day-to-day
privacy work. It is under active development – the building blocks:

- **Processing activities (RoPA)**: register under Art. 30 GDPR with
  versioning. Flow: "Draft" → "In review" → "Approved" → "Archived".
  Every approval creates an immutable version snapshot (including the
  TOM state).
- **Processors & DPAs**: register of service providers and contracts
  under Art. 28.
- **Data subject requests**: access, rectification, erasure,
  restriction, portability, objection (Art. 15–21) with a **30-day
  deadline** from receipt, identity verification, assignment and a
  documented decision.
- **TOM**: technical and organizational measures.
- **Privacy incidents**: recording with the 72-hour notification duty
  in mind (Art. 33). The report to the authority and the notification of the
  data subjects (Art. 34) are recorded separately.

Special characteristics:

- Request contents and decision notes are stored **encrypted** (a
  dedicated key per case).
- **Deliberately no admin bypass**: data protection permissions must be
  granted explicitly – platform admins do not receive them
  automatically.

Risks and irreversible actions: after the retention period the case
key can be destroyed (crypto-shredding) – the encrypted contents are
then **unrecoverable**. Approved RoPA versions can no longer be
changed.

Next steps: manage evidence (DPA documents, certificates) in the
**Documents** module.

The access report (Art. 15/20) can also be created for **club members** if
your organisation uses club management: master data with guardians, addresses
and bank details, and an overview of the club data (membership periods,
groups, attendance, fees, donations, grades, performances and more) with an
extract per area. The search finds members by name, email or member number.

All of the following pages are found in the sidebar under **Privacy**. To view
them, the read permission of the data protection module is sufficient
(exception: data subject portal); changes require a separate permission per
area. The **Data Protection** role has all of these permissions.

## Joint controllership

**Privacy** → **Records** → **Joint controllership**. The **Joint controller
agreement register** holds agreements on joint controllership under Art. 26
GDPR. The list shows **Title**, **Partner**, **Status** and **Essentials
provided**.

**Create new JCA**:

- **Partner (service provider)** from the service provider register and
  **Title** (required), optionally **Valid from** and **Joint point of
  contact**.
- **Responsibility matrix**: for **Information obligations (Art. 13/14)**,
  **Data subject rights**, **Data protection incidents** and **Supervisory
  authority contact** you set who is responsible in each case: **We**,
  **Partner** or **Joint** (default).
- Checkbox **Essentials of the joint controllership arrangement provided to
  data subjects**.
- Optionally the **Contract document** (PDF, DOC or DOCX, up to 20 MB).

A new agreement starts with the status **Draft**. In the agreement you change
the matrix, the **Point of contact**, the **Status** (**Draft**, **Active**,
**Terminated**, **Expired**) and **Essentials provided**. Under **Linked
processing activities** you tick the affected activities from the register
and save with **Save links**. You download the contract document via the link
in the key data.

**Permission:** viewing with the read permission; creating and changing with
the permission for service providers and DPAs.

## TOM catalog

**Privacy** → **Records** → **TOM catalog**. The catalog collects the
technical and organizational measures (Art. 32 GDPR) in one place. The list
shows **Measure**, **Area**, **Status** and **Review due**; if the review date
has passed, it is highlighted in red.

**New measure**: **Name**, **Measure area** (for example **Physical access
control**, **System access control**, **Data access control**, **Transfer control**, **Input control**,
**Availability control**, **Recoverability**, **Separation control** or
**Data protection management**), **Description**, **Addressed risks** and
**Evidence (policies, records, certificates …)**. The measure is created with
version 1 as a draft.

In the measure:

- **Versions**: you save changes via **New version** with description,
  addressed risks and **Change note**; older versions are kept. **Release**
  makes a version the **Valid version**.
- **Assigned processing activities**: choose an activity and **Assign**. When
  a processing activity is approved, its version also freezes the state of
  the assigned measures.
- **Effectiveness reviews**: **Document review** with **Result**
  (**Effective**, **Deviation** or **Ineffective**), optionally **Follow-up
  due** and **Deviation / follow-up action**. The next review is set to the
  follow-up date, or to one year later if there is no date; this date appears
  in the list under **Review due**.
- **Evidence**: store files with **Upload evidence**, optionally with **Valid
  until (optional)**. Expired evidence is marked; the gap analysis reports
  evidence that expires soon or has expired.

**Permission:** viewing with the read permission; creating, versioning,
releasing, assigning, reviewing and uploading evidence with the permission
for the TOM catalog.

## Gap analysis

**Privacy** → **Incidents & review** → **Gap analysis**. The analysis checks,
based on rules, whether contracts, assessments and evidence are missing or
expiring:

- processors without a DPA,
- DPAs that expire soon or have expired (by default 30 days in advance),
- joint controllers without a joint controller agreement,
- processing activities that need a DPIA but have no completed DPIA,
- processing activities without assigned TOMs,
- TOM evidence that expires soon or has expired.

**Run analysis now** starts a run. In addition, the analysis runs
automatically once a day as soon as the gap analysis has been opened once in
your organisation. At the top a traffic light shows the number of findings per
status; below are the findings – open ones first – with **Requirement**,
**Status**, **Trigger** and **Reference** (link to the activity, the DPA or
the service provider).

The analysis sets **Missing** or **Expires**. Under **Decision** you set
**Present**, **Under review**, **Not applicable**, **Deviation accepted** or
**Reopened** manually, each with **Justification** and **OK**. For “Not
applicable” and “Deviation accepted” the justification is required. Later
analysis runs no longer change a finding that was decided manually. Gaps set
by the analysis itself that no longer occur are set to **Present** by the next
run.

In the **Requirements catalog** at the bottom of the page you define which
checks run: each requirement can be renamed and deactivated with the switch;
deactivated requirements are skipped. Entries from an industry profile carry
the label **Industry profile**.

**Permission:** viewing with the read permission; starting the analysis,
deciding and maintaining the catalog with the permission for the gap
analysis.

## Retention & deletion

**Privacy** → **Incidents & review** → **Retention & deletion**. The deletion
concept proposes data whose retention period has expired for deletion;
nothing is deleted or anonymized before a two-step confirmation. At the top
you see your organisation's jurisdiction (for example DE), which determines
the periods.

- **Deadlines per area**: for each data area – such as the audit log,
  applications or the learning platform – the **Deadline** in years or days
  and the **Legal basis**. Areas marked “catalog entry only, no scan” only
  document the period; no proposals arise for them.
- **Scan now** looks for records whose period has expired and creates
  **Deletion suggestions**. The scan also runs automatically at regular
  intervals (weekly by default). Records under legal hold and specialist
  exceptions receive no proposal.
- Each proposal shows **Area**, **Record**, **Deadline expired since**,
  **Justification** and **Status**.

Deletion happens in two steps: first **Confirm** (status **acknowledged**) or
**Reject** (**declined**), then, for confirmed proposals, **Delete
permanently** – individually or bundled per area with **Delete confirmed in
…**. Depending on the area, the record is deleted or anonymized. If a legal
hold has been placed in the meantime, confirmation and deletion are rejected;
when deleting in bulk, such records are skipped and counted in the message.
Every decision is logged.

**Permission:** viewing with the read permission; scanning and deciding with
the permission for the gap analysis.

## Legal hold

**Privacy** → **Incidents & review** → **Legal hold**. A legal hold is a
blocking note for ongoing data subject or legal proceedings: as long as it is
active, nothing relating to the person or the customer is deleted or
anonymized.

The list shows active holds first, with **Affected** (name, person or
customer), **Reference number**, **Reason** (readable only for people with the
decision permission), **Placed** (date and by whom) and **Status** (**active**
or “released on …”).

**Place legal hold**: under **Type** choose **Person** or **Customer** and
then exactly one person in the organisation or one customer; optionally a
**Reference number**. The **Reason** is required (at least 10 characters) and
is stored encrypted.

As long as the hold is active, no deletion proposals arise; confirmed
deletions, anonymization and deleting accounts or customers are rejected, and
the person's raw location points are kept. When customers are merged, the
hold moves to the target customer.

**Release legal hold** requires a **Reason for release** (at least 10
characters). After that, the deletion concept and clean-up runs apply again;
the reason is kept as evidence. Placing and releasing appear in the log of the
person or customer.

**Permission:** viewing with the read permission; placing and releasing with
the permission for the gap analysis – whoever decides on deletions also
blocks them.

## Data subject portal

**Privacy** → **Incidents & review** → **Data subject portal** (page title
**Manage data subject portal**). Here you set up the public form through which
data subjects submit their requests; the topic on the access portal describes
how this works for the person.

- As long as no portal exists, the page shows a note; the first **Save**
  creates the portal with a random link.
- **Public link**: you publish this link in your privacy policy; it cannot be
  derived from the organisation name. Next to it you see whether the portal is
  **active** or **inactive**. **Rotate link** creates a new link after a
  confirmation prompt – links already published become invalid.
- **Settings**: **Portal active (publicly reachable)** – switched off
  initially –, **Allow attachments**, **Introductory text (optional)** and
  **Default language (optional, e.g. en)**.

Incoming requests appear as a case under **Data subject requests**. There
they are marked as portal intake; the identity details count as unverified
self-disclosure.

**Permission:** a separate permission for managing the data subject portal;
without it the menu entry is missing.
