---
title: "Data protection management at a glance"
topic: privacy.overview
version: 1
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

**Retention, deletion and legal hold:** Under **Retention & deletion** the
deletion concept proposes overdue data; nothing is deleted or anonymized before
a two-step confirmation. If a data subject or legal proceeding is ongoing, place
a hold on the person or customer under **Legal hold** – with a mandatory reason
and an optional case reference. While it is active, no deletion proposals arise,
confirmed deletions, anonymization and deleting accounts or customers are
rejected, and the person's raw location points are kept. When customers are
merged, the hold moves to the target customer. It is also released only with a
reason; both remain visible in the person's or customer's log.
