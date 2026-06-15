---
title: "Invite external participants"
topic: external.participants
version: 1
audience: []
related:
    - diary-entries.edit
    - protocols.create
---

External participants – **subcontractors, inspectors, experts** – can be
invited to an order, protocol or document in a **context-specific** and
**time-limited** way, **without a login** and without access to any other data.

How it works:

- **Invite**: Enter name, type (subcontractor/inspector/…), optionally email and
  role, and tick the **allowed actions** (comment, upload, confirm). Viewing is
  always allowed.
- **Validity**: 1 to 180 days. The access expires automatically afterwards.
- **Link**: After creation the access link is shown **exactly once** – copy it
  and send it to the external person. Only a hash is stored.

Security and evidence:

- The allowed actions are **strictly enforced server-side**. Someone with only
  "view" cannot upload or confirm.
- **Every external action** (access, comment, upload, confirmation) is logged
  traceably.
- Access can be **revoked** at any time – the link then becomes invalid
  immediately.
