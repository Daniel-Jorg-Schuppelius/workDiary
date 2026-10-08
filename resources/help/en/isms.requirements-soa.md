---
title: "Requirements & SoA"
topic: isms.requirements-soa
version: 3
keywords:
    - Statement of Applicability
    - applicability
    - requirements catalog
    - standards catalog
    - Annex A
    - ISO 27001
    - ISO 9001
    - ISO 27701
    - import catalog
    - not applicable justification
    - control objectives
audience: []
modules:
    - module.isms
related:
    - isms.overview
    - isms.controls
    - isms.conformity
    - glossary.core
---

This is where you manage the requirement catalogue and the **Statement
of Applicability (SoA)** per scope. You find the page under **ISMS** →
**Governance** → **Requirements & SoA**.

Typical workflow:

1. **Load standard catalogue**: choose and load a **Standard profile** –
   ISO/IEC 27001:2022 with the complete Annex A, plus ISO/IEC 27701,
   ISO 9001, ISO 22301, ISO 45001, ISO 37301 and ISO/IEC 42001 with their
   main clauses 4 to 10, as well as the NIST Cybersecurity Framework 2.0.
   Only the number and short title are loaded, no standard texts.
   Loading again never overwrites existing requirements or maintained
   SoA statements. Alternatively, **Import OSCAL** takes over a catalogue
   from a JSON file.
2. Optionally add your own requirements with **Add requirement**; their
   **Source** is then “Custom requirement” instead of “Reference
   catalogue”.
3. **Create SoA statements**: creates the missing statements for all
   requirements in the selected scope; existing ones remain unchanged.
   Then maintain each statement with **Edit SoA statement**.
4. Use the printable **SoA** view (**Print / save PDF**) for evidence and
   audits; **Export (CSV)** and **Export (JSON)** provide the data as a
   file.

Key fields per requirement: **Standard**, **Edition**, **Ref no.**
(e.g. “A.5.1”) and your own **Title** – deliberately no standard text.

Per SoA statement:

- **Applicable** yes/no – if “no”, a **Justification** is mandatory and
  the **Implementation status** automatically becomes **“Not
  applicable”**.
- **Implementation status**: “Open”, “Partially implemented”,
  “Implemented”, “Not applicable”.
- **Evidence note**: reference to evidence or a document.

Permissions: the **See ISMS registers (risks, controls, SoA)**
permission allows viewing. Catalogue imports and maintenance require
**Manage ISMS (risks, controls, catalogue import)**.

Next steps: link requirements to standard-neutral **Controls** (column
**Linked controls**) – the bridge from the “what” of the standard to
the “how” of your implementation.
