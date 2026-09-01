---
title: "Risk register"
topic: isms.risks
version: 1
audience: []
modules:
    - module.isms
related:
    - isms.controls
    - isms.overview
    - isms.audits
    - glossary.core
---

In the **risk register** you record, assess (5×5) and treat
information security risks per scope.

Typical workflow:

1. **Record a risk**: title, category ("Organizational", "Technical",
   "Physical", "Personnel", "Supplier"), affected asset,
   threat/vulnerability, owner.
2. **Assess**: likelihood (1–5) × impact (1–5) yields the score
   (1–25). Traffic light: up to 6 green (low), 7–12 yellow (medium),
   above 12 red (high).
3. Choose a **treatment**: "Avoid", "Mitigate", "Transfer" or
   "Accept" – and link controls.
4. Maintain the **status** along the chain: "Identified" →
   "Analyzed" → "Treated"/"Accepted" → "Closed".

Assessment history:

- Every assessment is historized as **gross**, **net** or **target**
  risk and moves from "Draft" to "Approved".
- **Approved assessments are immutable.**
- The most recent approved **net** assessment determines the values
  shown on the risk. If you change likelihood/impact directly on the
  risk, an approved direct assessment is created automatically – the
  history stays complete.

Important rule: switching to **"Accepted"** (residual risk
acceptance) requires an approved net assessment **with an
expiry/review date**.

Permissions: viewing requires ISMS read access; changes require ISMS
maintenance access.

Next steps: overdue risk reviews can be reported and escalated via
notification rules.
