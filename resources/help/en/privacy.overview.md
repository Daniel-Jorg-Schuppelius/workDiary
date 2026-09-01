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
  in mind (Art. 33/34).

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
