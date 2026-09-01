---
title: "Certifications & conformity"
topic: isms.conformity
version: 1
audience: []
modules:
    - module.isms
related:
    - isms.audits
    - isms.requirements-soa
    - isms.overview
    - glossary.core
---

On the **"Certifications"** page you maintain the conformity status
per standard and scope – from gap analysis to the recorded
certificate.

Typical workflow:

1. **Add a standard** (e.g. ISO/IEC 27001, edition 2022) per scope.
2. Move the status along the chain: "Not assessed" → "Gap analysis
   done" → "In progress" → "Internally audit-ready" → "External audit
   planned" → **"Certified"**. Returning to "In progress" is
   possible.
3. **Record the certificate**: certified organization, scope as
   stated on the certificate, certification body, certificate number,
   issue date, valid from/until – optionally surveillance audit dates
   and the certificate PDF.

Important rules:

- Switching to **"Certified"** is only possible with a certificate
  that is **valid today** and has all mandatory fields filled in. A
  maturity level, a completed checklist or the absence of open
  actions **never** triggers the status automatically.
- When the certificate expires, the deadline scanner automatically
  sets the status to **"Certificate expired"**. Suspension
  ("Certificate suspended") and resumption can be modeled;
  re-certification starts via "External audit planned".

Permissions: viewing requires ISMS read access; changes require ISMS
maintenance access.
Expiring certificates can be reported via notification rules.
