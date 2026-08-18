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


CI base design and inheritance:

- The org-wide default profile is your **CI base design**. Variants for
  individual document kinds (e.g. quote, invoice, credit note, dunning)
  or whole document families (sales, purchasing, evidence) **inherit**
  all sections that are not overridden — each section shows whether it
  is inherited or overridden; "reset to base design" removes the
  override. The more specific variant wins: kind before family before
  base design.
- The **embedded PDF preview** in the editor renders through the same
  pipeline as the final output; document kind and sample data (long
  texts, many items, multiple tax rates) are switchable.
- **Font family and base size** come from a curated, PDF-capable list;
  primary/accent colors can **reference the organization branding** —
  branding changes then apply automatically, without a color copy in
  the profile.
- On activation, the base design is checked against the mandatory
  blocks of ALL brandable document kinds; genuine special formats
  (e.g. labels) declare their restriction in the central document-kind
  registry.
