---
title: "Test equipment & calibration"
topic: asset-compliance.overview
version: 1
audience: []
related:
    - rental.overview
    - asset-finance.overview
---

The module manages inspection-relevant measuring devices, machines,
vehicles and installations: verification, calibration, DGUV/UVV, vehicle
inspection, electrical testing, manufacturer service and internal checks
— with evidence and usage blocks.

**Inspection profiles (catalogue):** global templates are overridden by
organisation profiles with the same code. Profiles carry interval,
warning lead time, tolerance, grace period and blocking mode — rule
changes are data maintenance, not a release.

**Inspection duties:** assigning a profile to an asset creates a duty
with due date and responsibility. Due inspections raise warnings; after
the grace period the system blocks via the shared blocking model —
rental, dispatch and usage read the same status.

**Reports & certificates:** inspections record measured values against
frozen limits, result, validity, signature and an optional calibration
certificate. Evidence is immutable — corrections are versioned.

**Exception releases** are time-limited, justified and audited per usage
context. **External inspectors** deliver evidence via a limited,
purpose-bound access. The **norm reference matrix** links inspection
kinds to legal sources without any conformity promise.
