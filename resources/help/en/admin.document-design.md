---
title: "Document design"
topic: admin.document-design
version: 1
audience:
    - admin
related:
    - admin.branding
    - invoices.manage
---

Document design adapts generated PDF documents to your
organisation's appearance: store letterheads, define print and blocked
areas, declare information blocks and pick a curated table style preset.

Workflow:

1. **Upload a letterhead** (PDF, JPG or PNG, A4 portrait) — one asset for
   the first page and optionally one for following pages. PDFs are reduced
   to a safe, non-interactive raster page; the original is kept as evidence.
2. **Create a profile** and use the editor to define print areas, address
   window, sender line and blocked areas in millimetres — visually or
   numerically, keyboard included.
3. **Declare information blocks**: `dynamic` (WorkDiary prints),
   `provided by letterhead` (with confirmation per profile version) or
   `not applicable`. Mandatory blocks of assigned document kinds and
   variable document data are protected.
4. **Generate a test document** per document kind with long texts, many
   line items and multiple tax rates; the preflight shows overlaps,
   missing mandatory blocks and contrast issues.
5. **Activate the version** — only with an error-free preflight. Activated
   versions are immutable; changes go through a new draft. Finalised
   documents keep their frozen state.

Without a profile the system default (current output) applies. ZUGFeRD/
PDF-A-3 invoices remain valid after applying the design — the structured
invoice stays authoritative.
