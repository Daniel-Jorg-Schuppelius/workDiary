---
title: "Incoming e-invoices"
topic: finance.incoming-invoices
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

This area receives incoming e-invoices, checks them and runs them through
a documented approval process — without touching the invoice sovereignty
of your leading accounting or invoicing software.

**Intake:** E-invoices arrive via file upload or through the e-mail
intake — as XRechnung (XML) or ZUGFeRD/Factur-X (PDF with embedded XML).
All channels go through exactly the same processing. The document is
stored in the DMS as an invoice-type document; the unmodified original
remains the single source, and the detail page re-reads it on every
visit. No local invoice is created.

**Duplicates:** Identical file content is captured only once per
organisation — across channels as well (an upload after a previous mail
intake is still a duplicate).

**Validation & consistency:** Every intake is validated against the XML
schema and, if configured, against the KoSIT rules (EN 16931); whether
these checks were available is shown transparently. In addition, the
deviation check warns visibly — never silently — about an already
captured invoice number from the same issuer, inconsistent totals (net +
tax ≠ gross) and tax shown without the issuer's tax identification.

**Suggestions:** For assignment, the system proposes suppliers (via VAT
ID or name similarity), purchase orders (via the order reference) and
projects (via the project/buyer reference) — as candidates with reasons.
Adoption stays with the reviewer; master data is never created or
changed automatically.

**Review workflow:** An intake is approved, put into question or rejected
(rejection requires a reason). Payment release is only possible after
the approval. Every decision is audited with person and time.

**Handover to accounting:** Only approved or payment-released intakes are
handed over. The handover is idempotent — a second call changes nothing
and creates no duplicate record.

**XML download:** The invoice XML can be extracted deterministically from
the original at any time (from the PDF attachment for ZUGFeRD). Every
download is logged with a checksum as proof.
