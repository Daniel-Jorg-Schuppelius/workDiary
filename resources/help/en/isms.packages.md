---
title: "Audit packages & auditor links"
topic: isms.packages
version: 1
audience: []
related:
    - isms.audits
    - isms.conformity
    - isms.overview
    - glossary.core
---

**Audit packages** freeze the ISMS data as a snapshot for a reporting
date – a reliable basis for external auditors.

Typical workflow:

1. **Create a package**: title, reporting date, scope, optionally a
   standard and edition as filter. The package starts as "Draft".
2. **Finalize**: creates the JSON snapshot with a SHA-256 hash and
   records who finalized it and when.
3. **Verify integrity**: compares the file against the stored hash at
   any time.
4. **Create an auditor link**: time-limited access (1–90 days),
   revocable at any time. The link opens a **read-only web view** of the
   finalised package — navigable, with the SHA-256 hash on the cover; the
   JSON package file is linked there for download. What is shown is always
   the state **frozen** at finalisation, never the live registers.

Package contents: SoA, risk register (latest approved net
assessments), control list with mappings, conformity status, audits
including findings and corrective actions, approved management
reviews, software inventory.

Risks and irreversible actions:

- **Finalized packages are immutable** – editing and deleting are
  blocked.
- The reporting date is the documented as-of date; the data state
  corresponds to the **moment of finalization** (no retroactive
  reconstruction).
- The full **auditor link is shown only once** (on creation) – after
  that, only revocation is possible.

Permissions: viewing requires ISMS read access; creation and
maintenance require ISMS maintenance access. The auditor download uses
a protected link and does not require a WorkDiary account.
