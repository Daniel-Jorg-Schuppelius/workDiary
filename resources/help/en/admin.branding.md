---
title: "Branding"
topic: admin.branding
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
    - invoices.manage
---

Branding configures the white-label appearance of the current
organization. The settings mainly affect generated PDF documents
(e.g. invoices) and are stored per tenant.

Editable areas:

- **Master data**: app name and slogan.
- **Contact**: address, phone, email and web.
- **Legal**: VAT ID, tax number, IBAN/BIC, bank and account holder,
  register entry and footer text.
- **Colors**: primary and accent color (hex).
- **PDF options per document type**: logo variant (light/dark/none)
  and display of the contact block and footer.

The actual **logo uploads** are handled through the attachment
management, not through this form.

Note: IBAN and BIC are normalized on save (spaces removed,
uppercased). Cleared fields fall back to the system defaults. The
"manage branding" permission and an existing organization context
are required.
