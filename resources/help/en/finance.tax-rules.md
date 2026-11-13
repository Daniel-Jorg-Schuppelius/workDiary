---
title: "Tax rule matrix"
topic: finance.tax-rules
version: 3
keywords:
    - tax rates
    - VAT
    - VAT rate
    - sales tax
    - reduced rate
    - reverse charge
    - small business exemption
    - tax exempt
    - import tax rules
    - tax rate change
    - zero rate
    - export outside EU
audience:
    - admin
modules:
    - module.finance
related:
    - invoices.manage
---

The tax rule matrix is the versioned catalogue from which local invoicing
determines its tax rates. WorkDiary ships a base catalogue; your
organisation's own rows override it — the shipped catalogue itself is
never modified.

**Structure:** Each rule applies to a country (optionally a region), a
category (services, goods, shipping, materials, expenses, construction,
media, other) and a rate type (standard, reduced, zero, exempt,
reverse_charge, export) — with a percentage, valid-from/valid-to dates, a
source reference and a note.

**Effective-date logic:** The service date is decisive, not the invoice
date. The most recent active rule valid on that date is applied;
organisation rows take precedence over the catalogue. If nothing specific
exists for a category, the services category acts as the fallback.

**Warnings:** When creating rules and during import, the overlap check
prevents two active rules of the same scope from overlapping in time. The
overview additionally warns about gaps in active rule chains — periods
for which no rule applies.

**CSV import:** Semicolon-separated file with the columns country,
category, rate_type, rate, valid_from, valid_to, source, note (header row
allowed). Lines with an unknown category/rate type or an overlap are
reported and skipped; the rest is imported.

**Retire instead of delete:** Rules are never deleted, only retired —
after that the catalogue or older rules apply again. Creating and
retiring rules is audited; only your organisation's own rows can be
retired.

**Freezing on issue:** When an invoice is issued, the tax context that was
actually used (rate, rule source, effective date, category, tax
breakdown) is frozen onto the document. Later rule changes therefore only
affect new documents, never issued ones.

**Special cases with precedence:** The small-business setting (German
§ 19 UStG) disables tax display entirely. A fixed default tax rate of the
organisation takes precedence over the matrix for domestic invoices. EU
customers with a formally valid VAT ID automatically receive reverse
charge (0 %), non-EU customers the export note (0 %) — matching matrix
rows provide the note text shown on the document.

For non-EU customers the tax case distinguishes goods from services: material
is an export (category G) with the note "Tax-exempt export supply"; a service to
a business outside the EU is not subject to German VAT (category O, § 3a (2)
UStG) and carries the note of the export rate type. The e-invoice, the exemption
note in the PDF and the accounting read the same category frozen at issue.

A row with the rate type **Exempt** names the exemption provision for
tax-exempt supplies under § 4 UStG: its note text appears in the e-invoice
as the exemption reason of the tax-exempt items (category E), as of the
invoice's date of supply, for example "Tax-exempt medical treatment (§ 4
no. 14 UStG)". Without it, the e-invoice only states "Tax-exempt supply".
If the tax case of an invoice draft changes, workDiary replaces the matrix
note text as long as it is still unchanged in the invoice text.
