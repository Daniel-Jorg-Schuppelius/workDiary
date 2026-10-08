---
title: "Risk register"
topic: isms.risks
version: 3
keywords:
    - risk analysis
    - risk assessment
    - risk matrix
    - add risk
    - risk inventory
    - risk treatment
    - residual risk
    - risk acceptance
    - likelihood
    - inherent risk
    - risk heat map
    - risk owner
audience: []
modules:
    - module.isms
related:
    - isms.controls
    - isms.overview
    - isms.audits
    - glossary.core
---

In the **Risk register** you record, assess (5×5) and treat information
security risks per scope. You find it under **ISMS** → **Governance** →
**Risk register**.

Typical workflow:

1. **Add risk**: **Title**, **Category** (“Organisational”,
   “Technical”, “Physical”, “Personnel”, “Supplier”), **Reference
   (system/process/site)**, **Threat** (the underlying threat or
   vulnerability), **Owner** and **Review due**.
2. **Assess**: **Likelihood** (1–5) × impact (1–5) yields the **Score**
   (1–25). Traffic light of the risk matrix: Low (score ≤ 6), Medium
   (score 7–12), High (score > 12).
3. Choose a **Treatment**: “Avoid”, “Mitigate”, “Transfer” or “Accept”
   – and assign controls under **Linked controls**.
4. Maintain the **Status** via **Change status** along the chain:
   “Identified” → “Analysed” → “Treated”/“Accepted” → “Closed”. A closed
   risk can be set back to “Analysed”.

Assessment history:

- **Record assessment** creates an assessment; the **Assessment type**
  is “Gross”, “Net” or “Target”. Every assessment has a **Rationale**,
  optionally a **Valid until** date (expiry or review date) and moves
  from “Draft” to “Approved” (**Approve**).
- **Approved assessments are immutable.**
- The most recent approved **Net** assessment determines the values
  shown on the risk. If you change likelihood or impact directly on the
  risk, an approved direct assessment is created automatically – the
  history stays complete.

Important rule: switching to **“Accepted”** (residual risk acceptance)
requires an approved net assessment **with a “Valid until” date**.

Permissions: viewing requires the **See ISMS registers (risks, controls,
SoA)** permission; changes require **Manage ISMS (risks, controls,
catalogue import)**.

Next steps: when the **Valid until** date of the most recent approved
net assessment of an open risk approaches or has passed, the owner is
notified; **Notification rules** can additionally escalate this. The
**Review due** field on the risk is for planning and sorting but does
not trigger a notification itself.
