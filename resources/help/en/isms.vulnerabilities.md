---
title: "Vulnerabilities & advisories"
topic: isms.vulnerabilities
version: 1
audience: []
modules:
    - module.isms
related:
    - isms.incidents
    - isms.software
    - isms.risks
    - glossary.core
---

In the **vulnerability register** you track known vulnerabilities with
severity, ownership and deadlines, and deliberately decide on their
exploitability.

Typical workflow:

1. **Record a vulnerability**: title, an optional identifier (such as a
   CVE number), the CVSS score and the affected component. Severity is
   derived from the CVSS score but can be overridden. Optionally link a
   product from the software inventory and set a deadline.
2. **Maintain the status**: from "Open" through "Under review" and
   "Mitigating" to "Resolved"; alternatively "Accepted" (a conscious
   residual risk) or "Not affected".
3. **Decide on exploitability**: determine whether the vulnerability is
   exploitable in your specific configuration. "Exploitable" and "Not
   exploitable" require a **mandatory rationale**.

**Import advisories** (CSAF/VEX): upload a machine-readable advisory as
JSON. The import matches the affected components against the software
inventory and the latest release bill of materials (SBOM) and creates one
vulnerability entry per match.

Important rule: an imported match is **not automatically considered
exploitable**. It starts under investigation; affectedness is a
deliberate, justified decision. If a VEX document states "not affected",
the rationale is carried over.

Evidence: every imported original advisory is stored with a checksum.
Re-importing the same file has no additional effect and creates no
duplicates.

Permissions: viewing requires ISMS read rights, maintenance and import
require ISMS maintenance rights.

Next steps: overdue vulnerabilities are reported and escalated.
