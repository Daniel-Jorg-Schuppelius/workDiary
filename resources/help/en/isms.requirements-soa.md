---
title: "Requirements & SoA"
topic: isms.requirements-soa
version: 1
audience: []
related:
    - isms.overview
    - isms.controls
    - isms.conformity
    - glossary.core
---

This is where you manage the requirement catalog and the **Statement
of Applicability (SoA)** per scope.

Typical workflow:

1. **Load a catalog**: import a standard profile (ISO/IEC 27001:2022
   with the complete Annex A, plus 27701, 9001, 22301, 45001, 37301,
   42001 at HLS level). The import is idempotent – existing
   requirements and SoA statements are never overwritten.
2. Optionally add **custom requirements** (source "Custom" instead of
   "Catalog").
3. **Create SoA statements**: generate them per scope for all
   requirements, then maintain each statement.
4. Use the printable **SoA view** for evidence and audits.

Key fields per requirement: **standard**, **edition**, **reference**
(e.g. "A.5.1") and your own **short title** – deliberately no standard
text.

Per SoA statement:

- **Applicable** yes/no – if "no", a **justification** is mandatory
  and the implementation status is forced to **"Not applicable"**.
- **Implementation status**: "Open", "Partial", "Implemented",
  "Not applicable".
- **Evidence**: reference to a document or proof.

Permissions: ISMS read access allows viewing. Catalog imports and
maintenance require ISMS maintenance access.

Next steps: link requirements to standard-neutral **controls** – the
bridge from the "what" of the standard to the "how" of your
implementation.
