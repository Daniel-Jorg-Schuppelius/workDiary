---
title: "Software inventory"
topic: isms.software
version: 1
audience: []
modules:
    - module.isms
related:
    - isms.overview
    - admin.security
    - glossary.core
---

The **software inventory** documents the software products in use and
their installations – including support status and end-of-life.

Typical workflow:

1. **Create a product**: name, vendor, leading version, category
   ("Operating system", "Application", "Service", "Library",
   "Other"), owner.
2. Maintain the **support status**: "Supported", "Extended support",
   "End-of-life", "Unknown" – plus the **EOL date**.
3. Record **installations**: installed version, location (e.g.
   "Server SRV-01, Notebook NB-12"), optionally an asset reference.

Automation: if the EOL date lies in the past, the support status is
automatically set to **"End-of-life"** on save – outdated products
become visible immediately.

Distinction: the inventory describes the software of **your
organization**. The components of the WorkDiary installation itself
(SBOM in CycloneDX format) are available to the platform admin in the
components overview of the administration area.

Permissions: viewing requires ISMS read access; changes require ISMS
maintenance access.

Next steps: the inventory state is included in **audit packages**
when they are finalized.
